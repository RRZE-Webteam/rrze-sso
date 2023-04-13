<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Authenticate
{
    /**
     * Settings options
     * @var object
     */
    protected $options;

    /**
     * \SimpleSAML\Auth\Simple
     * @var object
     */
    protected $authSimple;

    /**
     * Users can register
     * @var boolean
     */
    protected $registration;


    /**
     * Class constructor
     * @param object \SimpleSAML\Auth\Simple
     * @return void
     */
    public function __construct(\SimpleSAML\Auth\Simple $authSimple)
    {
        $this->options = Options::getOptions();
        $this->authSimple = $authSimple;
    }

    /**
     * loaded
     * @return void
     */
    public function loaded()
    {
        // Filters whether a set of user login credentials are valid.
        add_filter('authenticate', [$this, 'authenticate'], 10, 2);

        // Removed: Authenticates a user, confirming the username and password are valid.
        remove_action('authenticate', 'wp_authenticate_username_password', 20, 3);

        // Removed: Authenticates a user using the email and password.
        remove_action('authenticate', 'wp_authenticate_email_password', 20, 3);

        // Filters the login URL.
        add_filter('login_url', [$this, 'loginUrl'], 10, 2);

        // Fires after a user is logged out.
        add_action('wp_logout', [$this, 'logout']);

        // Filters whether the authentication check originated at the same domain.
        add_filter('wp_auth_check_same_domain', '__return_false');

        // Determines if user registration is enabled.
        if (is_multisite() && (!get_site_option('registration') || get_site_option('registration') == 'none')) {
            $this->registration = false;
        } elseif (!is_multisite() && !get_option('users_can_register')) {
            $this->registration = false;
        } else {
            $this->registration = true;
        }

        // Filters user registration enablement.
        $this->registration = apply_filters('rrze_sso_registration', $this->registration);
        // Filters user registration enablement (backward compatibility).
        $this->registration = apply_filters('fau_websso_registration', $this->registration);

        if (!$this->registration) {
            // Fires before the Site Sign-up page is loaded.
            add_action('before_signup_header', [$this, 'redirectToSiteUrl']);
        }
    }

    /**
     * Checks if a set of user login credentials is valid.
     * @param mixed $user null|WP_User|WP_Error
     * @param string $userLogin
     * @return object \WP_User
     */
    public function authenticate($user, $userLogin)
    {
        if (is_a($user, '\WP_User')) {
            return $user;
        }

        // Make sure that the user is authenticated.
        // If the user isn't authenticated, this function will start the authentication process.
        $this->authSimple->requireAuth();

        // Save the current session and clean any left overs that could interfere 
        // with the normal application behaviour.
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();

        // The entityID of the IdP the user is authenticated against.
        $samlSpIdp = $this->authSimple->getAuthData('saml:sp:IdP');

        // Retrieve the attributes of the current user.
        // If the user isn't authenticated, an empty array will be returned.
        if (empty($_atts = $this->authSimple->getAttributes())) {
            $this->authFailed(
                $this->authFailed(__("User attributes could not be retrieved.", 'rrze-sso'))
            );
        }

        // Process logging.
        do_action(
            'rrze.log.info',
            [
                'plugin' => plugin()->getBaseName(),
                'method' => __METHOD__,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'saml_sp_idp' => $samlSpIdp,
                'person_attributes' => $_atts
            ]
        );

        $atts = [];

        foreach ($_atts as $key => $value) {
            if (
                is_array($value)
                && in_array($key, ['uid', 'mail', 'displayName', 'cn', 'sn', 'givenName', 'o'])
            ) {
                $atts[$key] = $value[0];
            } else {
                $atts[$key] = $value;
            }
        }

        $domainScope = '';
        $identityProviders = simpleSAML()->getIdentityProviders();
        $userLogin = $atts['uid'] ?? '';

        foreach (array_keys($identityProviders) as $key) {
            $key = sanitize_title($key);
            $domainScope = $this->options->domain_scope[$key] ?? '';
            $domainScope = $domainScope ? '@' . $domainScope : $domainScope;
            if (sanitize_title($samlSpIdp) == $key) {
                $userLogin = $userLogin . $domainScope;
                break;
            }
        }

        $origUserLogin = $userLogin;
        $userLogin = preg_replace('/\s+/', '', substr(sanitize_user($userLogin), 0, 60));
        if ($userLogin != $origUserLogin) {
            $this->authFailed(__("The username entered is not valid.", 'rrze-sso'));
        }

        $userEmail = $atts['mail'] ?? '';
        $userEmail = is_email($atts['mail']) ? strtolower($atts['mail']) : sprintf('dummy.%s@rrze.sso', bin2hex(random_bytes(4)));

        $displayName = $atts['displayName'] ?? '';

        $lastName = $atts['sn'] ?? '';
        $lastName = $lastName ?: $atts['surname'] ?? '';

        $firstName = $atts['gn'] ?? '';
        $firstName = $firstName ?: $atts['givenName'] ?? '';

        $organizationName = $atts['o'] ?? '';
        $organizationName = $organizationName ?: $atts['organizationName'] ?? '';

        $eduPersonAffiliation = $atts['eduPersonAffiliation'] ?? '';
        $eduPersonScopedAffiliation = $atts['eduPersonScopedAffiliation'] ?? '';
        $eduPersonEntitlement = $atts['eduPersonEntitlement'] ?? '';

        if (is_multisite()) {
            global $wpdb;
            $key = $wpdb->get_var($wpdb->prepare("SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s", $userLogin));
            Users::activateSignup($key);
        }

        if ($userdata = get_user_by('login', $userLogin)) {
            $userId = $userdata->ID;
            $updateDisplayName = false;
            if ($firstName && !get_user_meta($userId, 'first_name', true)) {
                if (update_user_meta($userId, 'first_name', $firstName) === true) {
                    $updateDisplayName = true;
                }
            }
            if ($lastName && !get_user_meta($userId, 'last_name', true)) {
                if (update_user_meta($userId, 'last_name', $firstName) === true) {
                    $updateDisplayName = true;
                }
            }
            if ($displayName && $updateDisplayName) {
                wp_update_user(
                    [
                        'ID' => $userId,
                        'display_name' => $displayName
                    ]
                );
            }

            $user = new \WP_User($userId);
            update_user_meta($userId, 'saml_sp_idp', $samlSpIdp);
            update_user_meta($userId, 'organization_name', $organizationName);
            update_user_meta($userId, 'edu_person_affiliation', $eduPersonAffiliation);
            update_user_meta($userId, 'edu_person_scoped_affiliation', $eduPersonScopedAffiliation);
            update_user_meta($userId, 'edu_person_entitlement', $eduPersonEntitlement);

            if ($this->registration && is_multisite()) {
                if (!is_user_member_of_blog($userId, 1)) {
                    add_user_to_blog(1, $userId, 'subscriber');
                }
            }
        } elseif ($this->registration) {
            if (is_multisite()) {
                switch_to_blog(1);
            }

            $userId = wp_insert_user(
                [
                    'user_pass' => wp_generate_password(12, false),
                    'user_login' => $userLogin,
                    'user_email' => $userEmail,
                    'display_name' => $displayName,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'role' => 'subscriber'
                ]
            );

            if (is_wp_error($userId)) {
                if (is_multisite()) {
                    restore_current_blog();
                }
                $this->authFailed(__("The user could not be added.", 'rrze-sso'));
            }

            $user = new \WP_User($userId);
            update_user_meta($userId, 'saml_sp_idp', $samlSpIdp);
            update_user_meta($userId, 'organization_name', $organizationName);
            update_user_meta($userId, 'edu_person_affiliation', $eduPersonAffiliation);
            update_user_meta($userId, 'edu_person_scoped_affiliation', $eduPersonScopedAffiliation);
            update_user_meta($userId, 'edu_person_entitlement', $eduPersonEntitlement);

            if (is_multisite()) {
                add_user_to_blog(1, $userId, 'subscriber');
                restore_current_blog();

                if (!is_user_member_of_blog($userId, get_current_blog_id())) {
                    add_user_to_blog(get_current_blog_id(), $userId, 'subscriber');
                }
            }
        } else {
            $this->authFailed(
                sprintf(
                    /* translators: %s: username. */
                    __('The username "%s" is not registered on this website.', 'rrze-sso'),
                    $userLogin
                )
            );
        }

        if (is_multisite()) {
            $blogs = get_blogs_of_user($user->ID);
            if (!$this->hasDashboardAccess($user->ID, $blogs)) {
                $this->accessDenied($blogs);
            }
        }

        $ssoAtts = !empty($_atts) ? $_atts : '';
        update_user_meta($user->ID, 'sso_attributes', $ssoAtts);

        return $user;
    }

    /**
     * Check if the user has access to the website dashboard.
     * @param int $userId
     * @param mixed $blogs object|array
     * @return boolean
     */
    private function hasDashboardAccess($userId, $blogs)
    {
        if (is_super_admin($userId)) {
            return true;
        }
        if (wp_list_filter($blogs, ['userblog_id' => get_current_blog_id()])) {
            return true;
        }
        return false;
    }

    /**
     * Kills WordPress execution and displays an HTML page
     * with an authentication error message.
     * @param string $message
     * @param boolean $authSimple
     * @return void
     */
    private function authFailed($message, $authSimple = true)
    {
        $output = '';

        $output .= sprintf(
            '<p><strong>%1$s</strong> %2$s</p>',
            __("ERROR:", 'rrze-sso'),
            $message
        );
        $output .= sprintf(
            '<p>%s</p>',
            sprintf(
                /* translators: %s: name of the website. */
                __("Authentication failed on the &ldquo;%s&rdquo; website.", 'rrze-sso'),
                get_bloginfo('name')
            )
        );
        $output .= sprintf(
            '<p>%s</p>',
            __("However, if no login is possible, please contact the contact person of the website.", 'rrze-sso')
        );

        $output .= $this->getContacts();

        if ($authSimple) {
            $output .= sprintf(
                '<p><a href="%1$s">%2$s</a></p>',
                wp_logout_url(),
                __("Single Sign-On Log Out", 'rrze-sso')
            );
        }

        wp_die($output);
    }

    /**
     * Kills WordPress execution and displays an HTML page 
     * with an access denied message.
     * @param array $blogs
     * @return void
     */
    private function accessDenied($blogs)
    {
        $output = '<p>' . sprintf(
            /* translators: %s: name of the website. */
            __('You attempted to access the &ldquo;%1$s&rdquo; dashboard, but you do not currently have privileges on this website. If you believe you should be able to access the &ldquo;%1$s&rdquo; dashboard, please contact the contact person of the website.', 'rrze-sso'),
            get_bloginfo('name')
        ) . '</p>';

        if (!empty($blogs)) {
            $output .= '<p>' . __("If you reached this screen by accident and meant to visit one of your own websites, here are some shortcuts to help you find your way.", 'rrze-sso') . '</p>';

            $output .= '<h3>' . __("Your Websites", 'rrze-sso') . '</h3>';
            $output .= '<table>';

            foreach ($blogs as $blog) {
                $output .= '<tr>';
                $output .= "<td>{$blog->blogname}</td>";
                $output .= '<td><a href="' . esc_url(get_admin_url($blog->userblog_id)) . '">' . __("Visit the Dashboard", 'rrze-sso') . '</a> | ' .
                    '<a href="' . esc_url(get_home_url($blog->userblog_id)) . '">' . __("View the website", 'rrze-sso') . '</a></td>';
                $output .= '</tr>';
            }

            $output .= '</table>';
        }

        $output .= $this->getContacts();

        $output .= sprintf(
            '<p><a href="%1$s">%2$s</a></p>',
            wp_logout_url(),
            __("Single Sign-On Log Out", 'rrze-sso')
        );

        wp_die($output, 403);
    }

    /**
     * Get a list of website contact users (administrators).
     * @return string
     */
    private function getContacts()
    {
        $args = array(
            'role'    => 'administrator',
            'orderby' => 'display_name',
            'order'   => 'ASC'
        );

        if (empty($users = get_users($args))) {
            return '';
        }

        $output = sprintf(
            '<h3>%s</h3>' . PHP_EOL,
            sprintf(
                /* translators: %s: name of the website. */
                __("Contact persons for the &ldquo;%s&rdquo; website", 'rrze-sso'),
                get_bloginfo('name')
            )
        );

        foreach ($users as $user) {
            $output .= sprintf(
                '<p>%1$s<br/>%2$s %3$s</p>' . PHP_EOL,
                $user->display_name,
                __("Email Address:", 'rrze-sso'),
                make_clickable($user->user_email)
            );
        }

        return $output;
    }

    /**
     * Set the login URL.
     * @param string $loginUrl
     * @param string $redirect
     * @return string The login URL.
     */
    public function loginUrl($loginUrl, $redirect)
    {
        $loginUrl = site_url('wp-login.php', 'login');
        if (!empty($redirect)) {
            $loginUrl = add_query_arg('redirect_to', urlencode($redirect), $loginUrl);
        }
        return $loginUrl;
    }

    /**
     * Log the user out.
     * @return void
     */
    public function logout()
    {
        // Log the user out. After logging out, the user will be redirected to the home page.
        $this->authSimple->logout(site_url('', 'https'));
        // Save the current session and clean any left overs that could interfere 
        // with the normal application behaviour.        
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();
    }

    /**
     * Redirects to the home page.
     * @return void
     */
    public function redirectToSiteUrl()
    {
        wp_redirect(site_url('', 'https'));
        exit;
    }
}
