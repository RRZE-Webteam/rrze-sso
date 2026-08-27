<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Integrates SimpleSAMLphp authentication with WordPress user accounts.
 *
 * Registers the WordPress authentication hooks, maps SAML attributes to a
 * WordPress profile, synchronizes existing users, and creates users when
 * registration is enabled.
 */
class Authenticate
{
    /**
     * SAML attributes whose first value is used for the WordPress profile.
     */
    private const SINGLE_VALUE_ATTRIBUTES = array(
        'uid',
        'subject-id',
        'eduPersonUniqueId',
        'eduPersonPrincipalName',
        'mail',
        'displayName',
        'cn',
        'sn',
        'givenName',
        'o',
    );

    /**
     * Current plugin settings.
     *
     * @var \stdClass
     */
    protected $options;

    /**
     * SimpleSAMLphp authentication client.
     *
     * @var \SimpleSAML\Auth\Simple
     */
    protected $authSimple;

    /**
     * Whether new WordPress users may be registered.
     *
     * @var bool
     */
    protected $registration = false;

    /**
     * Initializes the authenticator with a SimpleSAMLphp client.
     *
     * @param \SimpleSAML\Auth\Simple $authSimple SimpleSAMLphp authentication client.
     */
    public function __construct(\SimpleSAML\Auth\Simple $authSimple)
    {
        $this->options = Options::getOptions();
        $this->authSimple = $authSimple;
    }

    /**
     * Registers authentication, login, logout, and registration hooks.
     *
     * @return void
     */
    public function loaded()
    {
        add_filter('authenticate', [$this, 'authenticate'], 10, 2);
        remove_action('authenticate', 'wp_authenticate_username_password', 20, 3);
        remove_action('authenticate', 'wp_authenticate_email_password', 20, 3);
        add_filter('login_url', [$this, 'loginUrl'], 10, 2);
        add_action('wp_logout', [$this, 'logout']);
        add_filter('wp_auth_check_same_domain', '__return_false');

        $registration = self::isRegistrationEnabled();
        $registration = apply_filters('rrze_sso_registration', $registration);
        $registration = apply_filters('fau_websso_registration', $registration);
        $this->registration = (bool) $registration;

        if (!$this->registration) {
            add_action('before_signup_header', [$this, 'redirectToSiteUrl']);
        }
    }

    /**
     * Determines whether WordPress user registration is enabled.
     *
     * @return bool Whether registration is enabled.
     */
    private static function isRegistrationEnabled(): bool
    {
        if (is_multisite()) {
            $registration = get_site_option('registration');

            return (bool) $registration && 'none' !== $registration;
        }

        return (bool) get_option('users_can_register');
    }

    /**
     * Authenticates a WordPress user through SimpleSAMLphp.
     *
     * The `$userLogin` argument remains part of the public callback signature
     * for compatibility. The authoritative login is derived from SAML data.
     *
     * @param mixed  $user      Existing authentication result.
     * @param string $userLogin Login submitted to WordPress.
     * @return \WP_User Existing or newly created authenticated user.
     */
    public function authenticate($user, $userLogin)
    {
        if (is_a($user, '\WP_User')) {
            return $user;
        }

        $this->startSamlAuthentication();

        $identityProviderId = $this->samlIdentityProviderId();
        $rawAttributes = $this->samlAttributes();
        $this->logAuthentication($identityProviderId, $rawAttributes);

        $attributes = self::normalizeAttributes($rawAttributes);
        $identityProvider = $this->resolveIdentityProvider($identityProviderId);
        $userLogin = $this->resolveUserLogin($attributes, $identityProvider['key']);
        $profile = $this->buildUserProfile($attributes, $identityProvider['name']);

        $this->activatePendingSignup($userLogin);
        $user = $this->resolveWordPressUser(
            $userLogin,
            $profile,
            $identityProviderId
        );

        $this->ensureDashboardAccess($user);
        update_user_meta($user->ID, 'sso_attributes', $rawAttributes);

        return $user;
    }

    /**
     * Starts SAML authentication and clears request-session leftovers.
     *
     * @return void
     */
    private function startSamlAuthentication(): void
    {
        $this->authSimple->requireAuth();
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();
    }

    /**
     * Returns the entity ID of the authenticating identity provider.
     *
     * @return string Identity-provider entity ID.
     */
    private function samlIdentityProviderId(): string
    {
        $identityProviderId = $this->authSimple->getAuthData('saml:sp:IdP');

        return is_scalar($identityProviderId) ? (string) $identityProviderId : '';
    }

    /**
     * Retrieves the authenticated user's raw SAML attributes.
     *
     * @return array<string, mixed> Raw SAML attributes.
     */
    private function samlAttributes(): array
    {
        $attributes = $this->authSimple->getAttributes();

        if (!is_array($attributes) || !$attributes) {
            $this->authFailed(__('User attributes could not be retrieved.', 'rrze-sso'));

            return array();
        }

        return $attributes;
    }

    /**
     * Writes the successful SAML response details to the plugin log hook.
     *
     * @param string               $identityProviderId Identity-provider entity ID.
     * @param array<string, mixed> $attributes         Raw SAML attributes.
     * @return void
     */
    private function logAuthentication(string $identityProviderId, array $attributes): void
    {
        do_action(
            'rrze.log.info',
            array(
                'plugin' => plugin()->getBaseName(),
                'method' => __CLASS__ . '::authenticate',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'saml_sp_idp' => $identityProviderId,
                'person_attributes' => $attributes,
            )
        );
    }

    /**
     * Normalizes known SAML attributes to their first scalar value.
     *
     * Namespaced attribute keys retain only the final segment for attributes
     * used by the WordPress profile. Other attributes remain unchanged.
     *
     * @param array<string, mixed> $attributes Raw SAML attributes.
     * @return array<string, mixed> Normalized SAML attributes.
     */
    private static function normalizeAttributes(array $attributes): array
    {
        $normalized = array();

        foreach ($attributes as $key => $value) {
            $segments = explode(':', $key);
            $normalizedKey = $segments[array_key_last($segments)];

            if (is_array($value) && in_array($normalizedKey, self::SINGLE_VALUE_ATTRIBUTES, true)) {
                $normalized[$normalizedKey] = $value[0] ?? '';
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Resolves the configured identity provider used for authentication.
     *
     * @param string $identityProviderId Identity-provider entity ID.
     * @return array{key: string, name: string} Sanitized provider key and name.
     */
    private function resolveIdentityProvider(string $identityProviderId): array
    {
        $providers = simpleSAML()->getIdentityProviders();
        $providers = is_array($providers) ? $providers : array();
        $requestedKey = sanitize_title($identityProviderId);

        foreach ($providers as $providerKey => $providerName) {
            $providerKey = sanitize_title($providerKey);
            if ($requestedKey !== $providerKey) {
                continue;
            }

            return array(
                'key' => $providerKey,
                'name' => is_scalar($providerName)
                    ? sanitize_text_field((string) $providerName)
                    : '',
            );
        }

        $this->authFailed(
            sprintf(
                /* translators: %s: IdP name. */
                __('The IdP &ldquo;%s&rdquo; is not registered on this SP.', 'rrze-sso'),
                esc_html($identityProviderId)
            )
        );

        return array('key' => '', 'name' => '');
    }

    /**
     * Builds and validates the WordPress login from SAML attributes.
     *
     * @param array<string, mixed> $attributes          Normalized SAML attributes.
     * @param string               $identityProviderKey Sanitized provider key.
     * @return string Validated WordPress login.
     */
    private function resolveUserLogin(array $attributes, string $identityProviderKey): string
    {
        $userLogin = self::firstAttributeValue($attributes, array('uid'));

        if (!$userLogin) {
            $subjectId = self::firstAttributeValue(
                $attributes,
                array('subject-id', 'eduPersonUniqueId', 'eduPersonPrincipalName')
            );
            $userLogin = explode('@', $subjectId)[0] ?? '';
        }

        if (!$userLogin) {
            $this->authFailed(
                __('User login could not be determined from SAML attributes.', 'rrze-sso')
            );
        }

        $domainScope = $this->options->domain_scope[$identityProviderKey] ?? '';
        if (is_scalar($domainScope) && $domainScope) {
            $userLogin .= '@' . $domainScope;
        }

        $sanitizedLogin = substr(Users::sanitizeUserName($userLogin), 0, 60);
        if ($sanitizedLogin !== $userLogin) {
            $this->authFailed(__('The username entered is not valid.', 'rrze-sso'));
        }

        return $sanitizedLogin;
    }

    /**
     * Builds WordPress profile data from normalized SAML attributes.
     *
     * @param array<string, mixed> $attributes           Normalized SAML attributes.
     * @param string               $identityProviderName Display name of the identity provider.
     * @return array<string, mixed> WordPress profile and SAML metadata values.
     */
    private function buildUserProfile(array $attributes, string $identityProviderName): array
    {
        $email = self::firstAttributeValue($attributes, array('mail'));
        $email = is_email($email)
            ? strtolower($email)
            : sprintf('dummy.%s@rrze.sso', bin2hex(random_bytes(4)));

        $organizationName = self::firstAttributeValue(
            $attributes,
            array('o', 'organizationName')
        );

        return array(
            'user_email' => $email,
            'display_name' => self::firstAttributeValue($attributes, array('displayName')),
            'first_name' => self::firstAttributeValue($attributes, array('gn', 'givenName')),
            'last_name' => self::firstAttributeValue($attributes, array('sn', 'surname')),
            'organization_name' => $organizationName ?: $identityProviderName,
            'edu_person_affiliation' => $attributes['eduPersonAffiliation'] ?? '',
            'edu_person_scoped_affiliation' => $attributes['eduPersonScopedAffiliation'] ?? '',
            'edu_person_entitlement' => $attributes['eduPersonEntitlement'] ?? '',
        );
    }

    /**
     * Returns the first non-empty scalar value among a list of attributes.
     *
     * @param array<string, mixed> $attributes Attribute map.
     * @param array<int, string>   $keys       Attribute keys in priority order.
     * @return string Attribute value, or an empty string.
     */
    private static function firstAttributeValue(array $attributes, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $attributes[$key] ?? '';

            if (is_array($value)) {
                $value = $value[0] ?? '';
            }

            if (is_scalar($value) && '' !== (string) $value) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * Activates a matching pending Multisite signup, when one exists.
     *
     * @param string $userLogin WordPress user login.
     * @return void
     */
    private function activatePendingSignup(string $userLogin): void
    {
        if (!is_multisite()) {
            return;
        }

        global $wpdb;

        $key = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s",
                $userLogin
            )
        );

        Users::activateSignup($key);
    }

    /**
     * Resolves an existing WordPress user or creates one when permitted.
     *
     * @param string               $userLogin         WordPress user login.
     * @param array<string, mixed> $profile           Profile and SAML metadata.
     * @param string               $identityProviderId Identity-provider entity ID.
     * @return \WP_User Authenticated WordPress user.
     */
    private function resolveWordPressUser(
        string $userLogin,
        array $profile,
        string $identityProviderId
    ): \WP_User {
        $userData = get_user_by('login', $userLogin);

        if ($userData) {
            return $this->synchronizeExistingUser($userData, $profile, $identityProviderId);
        }

        if (!$this->registration) {
            $this->authFailed(
                sprintf(
                    /* translators: %s: username. */
                    __('The username "%s" is not registered on this website.', 'rrze-sso'),
                    esc_html($userLogin)
                )
            );
        }

        return $this->createUser($userLogin, $profile, $identityProviderId);
    }

    /**
     * Synchronizes profile and SAML metadata for an existing user.
     *
     * @param \WP_User            $userData           Existing WordPress user.
     * @param array<string, mixed> $profile            Profile and SAML metadata.
     * @param string               $identityProviderId Identity-provider entity ID.
     * @return \WP_User Synchronized WordPress user.
     */
    private function synchronizeExistingUser(
        \WP_User $userData,
        array $profile,
        string $identityProviderId
    ): \WP_User {
        $userId = (int) $userData->ID;

        $this->updateNameFields($userData, $profile);
        $this->updateSamlUserMeta($userId, $identityProviderId, $profile);

        if ($this->registration && is_multisite() && !is_user_member_of_blog($userId, 1)) {
            add_user_to_blog(1, $userId, 'subscriber');
        }

        return new \WP_User($userId);
    }

    /**
     * Updates mutable WordPress name fields when SAML values have changed.
     *
     * @param \WP_User            $userData Existing WordPress user.
     * @param array<string, mixed> $profile  Profile values.
     * @return void
     */
    private function updateNameFields(\WP_User $userData, array $profile): void
    {
        $userId = (int) $userData->ID;

        if ($profile['first_name'] && $profile['first_name'] != get_user_meta($userId, 'first_name', true)) {
            update_user_meta($userId, 'first_name', $profile['first_name']);
        }

        if ($profile['last_name'] && $profile['last_name'] != get_user_meta($userId, 'last_name', true)) {
            update_user_meta($userId, 'last_name', $profile['last_name']);
        }

        if ($profile['display_name'] && $profile['display_name'] != $userData->display_name) {
            wp_update_user(
                array(
                    'ID' => $userId,
                    'display_name' => $profile['display_name'],
                )
            );
        }
    }

    /**
     * Creates a WordPress user from the resolved SAML profile.
     *
     * @param string               $userLogin         WordPress user login.
     * @param array<string, mixed> $profile           Profile and SAML metadata.
     * @param string               $identityProviderId Identity-provider entity ID.
     * @return \WP_User Newly created WordPress user.
     */
    private function createUser(
        string $userLogin,
        array $profile,
        string $identityProviderId
    ): \WP_User {
        $isMultisite = is_multisite();

        if ($isMultisite) {
            switch_to_blog(1);
        }

        $userId = wp_insert_user(
            array(
                'user_pass' => wp_generate_password(12, false),
                'user_login' => $userLogin,
                'user_email' => $profile['user_email'],
                'display_name' => $profile['display_name'],
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'role' => 'subscriber',
            )
        );

        if (is_wp_error($userId)) {
            if ($isMultisite) {
                restore_current_blog();
            }

            $this->authFailed(__('The user could not be added.', 'rrze-sso'));
        }

        $userId = (int) $userId;
        $user = new \WP_User($userId);
        $this->updateSamlUserMeta($userId, $identityProviderId, $profile);

        if ($isMultisite) {
            add_user_to_blog(1, $userId, 'subscriber');
            restore_current_blog();

            $currentBlogId = get_current_blog_id();
            if (!is_user_member_of_blog($userId, $currentBlogId)) {
                add_user_to_blog($currentBlogId, $userId, 'subscriber');
            }
        }

        return $user;
    }

    /**
     * Persists identity-provider and SAML profile metadata for a user.
     *
     * @param int                  $userId             WordPress user ID.
     * @param string               $identityProviderId Identity-provider entity ID.
     * @param array<string, mixed> $profile            SAML metadata values.
     * @return void
     */
    private function updateSamlUserMeta(
        int $userId,
        string $identityProviderId,
        array $profile
    ): void {
        update_user_meta($userId, 'saml_sp_idp', $identityProviderId);
        update_user_meta($userId, 'organization_name', $profile['organization_name']);
        update_user_meta($userId, 'edu_person_affiliation', $profile['edu_person_affiliation']);
        update_user_meta($userId, 'edu_person_scoped_affiliation', $profile['edu_person_scoped_affiliation']);
        update_user_meta($userId, 'edu_person_entitlement', $profile['edu_person_entitlement']);
    }

    /**
     * Rejects Multisite users who cannot access the current dashboard.
     *
     * @param \WP_User $user Authenticated WordPress user.
     * @return void
     */
    private function ensureDashboardAccess(\WP_User $user): void
    {
        if (!is_multisite()) {
            return;
        }

        $blogs = get_blogs_of_user($user->ID);
        if (!$this->hasDashboardAccess($user->ID, $blogs)) {
            $this->accessDenied($blogs);
        }
    }

    /**
     * Determines whether a user can access the current site dashboard.
     *
     * @param int                $userId WordPress user ID.
     * @param array<int, object> $blogs  Sites available to the user.
     * @return bool Whether dashboard access is allowed.
     */
    private function hasDashboardAccess(int $userId, array $blogs): bool
    {
        if (is_super_admin($userId)) {
            return true;
        }

        return (bool) wp_list_filter(
            $blogs,
            array('userblog_id' => get_current_blog_id())
        );
    }

    /**
     * Stops execution with an authentication error page.
     *
     * @param string $message        Authentication error message.
     * @param bool   $showLogoutLink Whether to include the SSO logout link.
     * @return void
     */
    private function authFailed(string $message, bool $showLogoutLink = true): void
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

        if ($showLogoutLink) {
            $output .= sprintf(
                '<p><a href="%1$s">%2$s</a></p>',
                wp_logout_url(),
                __("Single Sign-On Log Out", 'rrze-sso')
            );
        }

        wp_die($output);
    }

    /**
     * Stops execution with a dashboard access-denied page.
     *
     * @param array<int, object> $blogs Sites available to the user.
     * @return void
     */
    private function accessDenied(array $blogs): void
    {
        $output = '<p>' . sprintf(
            /* translators: %s: name of the website. */
            __('You attempted to access the &ldquo;%1$s&rdquo; dashboard, but you do not currently have privileges on this website. If you believe you should be able to access the &ldquo;%1$s&rdquo; dashboard, please contact the contact person of the website.', 'rrze-sso'),
            get_bloginfo('name')
        ) . '</p>';

        $output .= $this->dashboardLinks($blogs);

        $output .= $this->getContacts();

        $output .= sprintf(
            '<p><a href="%1$s">%2$s</a></p>',
            wp_logout_url(),
            __("Single Sign-On Log Out", 'rrze-sso')
        );

        wp_die($output, 403);
    }

    /**
     * Builds shortcuts to dashboards available to the current user.
     *
     * @param array<int, object> $blogs Sites available to the user.
     * @return string Dashboard shortcuts markup, or an empty string.
     */
    private function dashboardLinks(array $blogs): string
    {
        if (!$blogs) {
            return '';
        }

        $output = '<p>' . __('If you reached this screen by accident and meant to visit one of your own websites, here are some shortcuts to help you find your way.', 'rrze-sso') . '</p>';
        $output .= '<h3>' . __('Your Websites', 'rrze-sso') . '</h3>';
        $output .= '<table>';

        foreach ($blogs as $blog) {
            $output .= '<tr>';
            $output .= "<td>{$blog->blogname}</td>";
            $output .= '<td><a href="' . esc_url(get_admin_url($blog->userblog_id)) . '">' . __('Visit the Dashboard', 'rrze-sso') . '</a> | ' .
                '<a href="' . esc_url(get_home_url($blog->userblog_id)) . '">' . __('View the website', 'rrze-sso') . '</a></td>';
            $output .= '</tr>';
        }

        return $output . '</table>';
    }

    /**
     * Builds a list of site administrators who can be contacted for help.
     *
     * @return string Contact list markup, or an empty string.
     */
    private function getContacts(): string
    {
        $args = array(
            'role'    => 'administrator',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        );

        $users = get_users($args);
        if (!$users) {
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
     * Replaces the WordPress login URL while retaining its redirect target.
     *
     * @param string $loginUrl Existing login URL.
     * @param string $redirect Requested post-login redirect URL.
     * @return string SSO-compatible login URL.
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
     * Logs the current user out of SimpleSAMLphp and cleans its session.
     *
     * @param int $userId WordPress user ID supplied by the logout action.
     * @return void
     */
    public function logout($userId)
    {
        $this->authSimple->logout(site_url('', 'https'));
        \SimpleSAML\Session::getSessionFromRequest()->cleanup();
    }

    /**
     * Redirects disabled sign-up requests to the HTTPS site home page.
     *
     * @return void
     */
    public function redirectToSiteUrl()
    {
        wp_redirect(site_url('', 'https'));
        exit;
    }
}
