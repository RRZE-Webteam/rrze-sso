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
    protected $simplesamlAuthSimple;

    /**
     * Users can register
     * @var boolean
     */
    protected $registration;


    /**
     * Class constructor
     *
     * @param object \SimpleSAML\Auth\Simple
     * @return bool
     */
    public function __construct($simplesamlAuthSimple)
    {
        $this->options = Options::getOptions();
        if (!is_a($simplesamlAuthSimple, '\SimpleSAML\Auth\Simple')) {
            return false;
        }
        $this->simplesamlAuthSimple = $simplesamlAuthSimple;
        return true;
    }

    public function onLoaded()
    {
        add_filter('authenticate', [$this, 'authenticate'], 10, 2);
        remove_action('authenticate', 'wp_authenticate_username_password', 20, 3);
        remove_action('authenticate', 'wp_authenticate_email_password', 20, 3);

        add_filter('login_url', [$this, 'loginUrl'], 10, 2);

        add_action('wp_logout', [$this, 'wpLogout']);
        // Filters whether to load the authentication check.
        add_filter('wp_auth_check_load', '__return_false');

        add_action('admin_init', [$this, 'isUserLoggedIn']);

        if (is_multisite() && (!get_site_option('registration') || get_site_option('registration') == 'none')) {
            $this->registration = false;
        } elseif (!is_multisite() && !get_option('users_can_register')) {
            $this->registration = false;
        } else {
            $this->registration = true;
        }

        $this->registration = apply_filters('rrze_sso_registration', $this->registration);
        // Backward compatibility
        $this->registration = apply_filters('fau_websso_registration', $this->registration);

        if (!$this->registration) {
            add_action('before_signup_header', [$this, 'beforeSignupHeader']);
        }
    }

    public function isUserLoggedIn()
    {
        if (
            is_user_logged_in()
            && !$this->simplesamlAuthSimple->isAuthenticated()
        ) {
            wp_destroy_current_session();
            wp_clear_auth_cookie();
            wp_set_current_user(0);
        }
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();
    }

    public function authenticate($user, $userLogin)
    {
        if (is_a($user, '\WP_User')) {
            return $user;
        }

        $this->simplesamlAuthSimple->requireAuth();
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();

        $samlSpIdp = $this->simplesamlAuthSimple->getAuthData('saml:sp:IdP');

        $atts = [];

        $_atts = $this->simplesamlAuthSimple->getAttributes();

        if (!empty($_atts)) {
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

            foreach ($_atts as $key => $value) {
                if (
                    is_array($value)
                    && in_array($key, ['uid', 'subject-id', 'eduPersonUniqueId', 'eduPersonPrincipalName', 'mail', 'displayName', 'cn', 'sn', 'givenName', 'o'])
                ) {
                    $atts[$key] = $value[0];
                } else {
                    $atts[$key] = $value;
                }
            }
        }

        $domainScope = '';
        $userLogin = $atts['uid'] ?? '';
        $subjectId = $atts['subject-id'] ?? $atts['eduPersonUniqueId'] ?? $atts['eduPersonPrincipalName'] ?? '';

        if (strpos($subjectId, '@') !== false) {
            $domainScope = explode('@', $subjectId)[1];
        }

        foreach ($this->options->domain_scope as $domain) {
            if (strpos($domain, $domainScope) !== false) {
                if (strpos($domain, '>') !== false) {
                    $domainScope = explode('>', $domain)[1];
                }
                $userLogin = explode('@', $subjectId)[0] . '@' . $domainScope;
            }
        }

        $origUserLogin = $userLogin;
        $userLogin = preg_replace('/\s+/', '', substr(sanitize_user($userLogin), 0, 60));
        if ($userLogin != $origUserLogin) {
            $this->loginDie(__("The username entered is not valid.", 'rrze-sso'));
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
            if ($displayName && $userdata->data->display_name == $userLogin) {
                $userId = wp_update_user(
                    [
                        'ID' => $userdata->ID,
                        'display_name' => $displayName
                    ]
                );
                update_user_meta($userId, 'first_name', $firstName);
                update_user_meta($userId, 'last_name', $lastName);
            }

            $user = new \WP_User($userdata->ID);
            update_user_meta($userdata->ID, 'saml_sp_idp', $samlSpIdp);
            update_user_meta($userdata->ID, 'organization_name', $organizationName);
            update_user_meta($userdata->ID, 'edu_person_affiliation', $eduPersonAffiliation);
            update_user_meta($userdata->ID, 'edu_person_scoped_affiliation', $eduPersonScopedAffiliation);
            update_user_meta($userdata->ID, 'edu_person_entitlement', $eduPersonEntitlement);

            if ($this->registration && is_multisite()) {
                if (!is_user_member_of_blog($userdata->ID, 1)) {
                    add_user_to_blog(1, $userdata->ID, 'subscriber');
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
                $this->loginDie(__("The user could not be added.", 'rrze-sso'));
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
            $this->loginDie(
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
                $this->accessDie403($blogs);
            }
        }

        $ssoAtts = !empty($_atts) ? $_atts : '';
        update_user_meta($user->ID, 'sso_attributes', $ssoAtts);

        return $user;
    }

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

    private function loginDie($message, $simplesamlAuthSimple = true)
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

        $output .= $this->getContact();

        if ($simplesamlAuthSimple) {
            $output .= sprintf(
                '<p><a href="%1$s">%2$s</a></p>',
                wp_logout_url(),
                __("Single Sign-On Log Out", 'rrze-sso')
            );
        }

        wp_die($output);
    }

    private function accessDie403($blogs)
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

        $output .= $this->getContact();

        $output .= sprintf(
            '<p><a href="%1$s">%2$s</a></p>',
            wp_logout_url(),
            __("Single Sign-On Log Out", 'rrze-sso')
        );

        wp_die($output, 403);
    }

    private function getContact()
    {
        global $wpdb;

        $blog_prefix = $wpdb->get_blog_prefix(get_current_blog_id());
        $users = $wpdb->get_results(
            "SELECT user_id, user_id AS ID, user_login, display_name, user_email, meta_value
             FROM $wpdb->users, $wpdb->usermeta
             WHERE {$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND meta_key = '{$blog_prefix}capabilities'
             ORDER BY {$wpdb->usermeta}.user_id"
        );

        if (empty($users)) {
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
            $roles = unserialize($user->meta_value);
            if (isset($roles['administrator'])) {
                $output .= sprintf(
                    '<p>%1$s<br/>%2$s %3$s</p>' . PHP_EOL,
                    $user->display_name,
                    __("Email Address:", 'rrze-sso'),
                    make_clickable($user->user_email)
                );
            }
        }

        return $output;
    }

    public function loginUrl($loginUrl, $redirect)
    {
        $loginUrl = site_url('wp-login.php', 'login');

        if (!empty($redirect)) {
            $loginUrl = add_query_arg('redirect_to', urlencode($redirect), $loginUrl);
        }

        return $loginUrl;
    }

    public function wpLogout()
    {
        $this->simplesamlAuthSimple->logout(site_url('', 'https'));
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();
    }
}
