<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Handles user-management form submissions from the administration area.
 */
class UserRegistrationHandler
{
    /** Network Admin form action for creating a user. */
    private const NETWORK_ADD_USER_ACTION = '_network_add-user';

    /** Site Admin form action for adding an existing user. */
    private const ADMIN_ADD_USER_ACTION = '_admin_add-user';

    /** Site Admin form action for creating a user. */
    private const ADMIN_CREATE_USER_ACTION = '_admin_create-user';

    /**
     * Dispatches the current add-user request to its dedicated handler.
     *
     * @return void
     */
    public static function handle(): void
    {
        $action = sanitize_key(self::requestValue('action'));
        $redirect = null;

        if (self::NETWORK_ADD_USER_ACTION === $action) {
            $redirect = self::handleNetworkUserCreation();
        } elseif (self::ADMIN_ADD_USER_ACTION === $action && isset($_REQUEST['email'])) {
            $redirect = self::handleExistingUserAddition();
        } elseif (self::ADMIN_CREATE_USER_ACTION === $action) {
            $redirect = self::handleUserCreation();
        }

        if (null === $redirect) {
            return;
        }

        wp_redirect($redirect);
        exit;
    }

    /**
     * Creates a user from the Network Admin add-user form.
     *
     * @return string Redirect URL after processing the request.
     */
    private static function handleNetworkUserCreation(): string
    {
        check_admin_referer('add-user', '_wpnonce_add-user');
        self::requireCapability(
            'manage_network_users',
            __('Sorry, you are not allowed to add users to this network.')
        );

        if (!isset($_POST['user']) || !is_array($_POST['user'])) {
            wp_die(__('Cannot create an empty user.'));
        }

        $user = wp_unslash($_POST['user']);
        $identity_provider = self::arrayStringValue($user, 'idp');
        $username = self::arrayStringValue($user, 'username');
        $email = self::arrayStringValue($user, 'email');
        $user_details = UserSignupValidator::validate($identity_provider, $username, $email);

        if (self::hasValidationErrors($user_details)) {
            return self::errorRedirect(
                $user_details['errors'],
                array('update' => 'addusererrors')
            );
        }

        $scoped_username = Username::addDomainScope($username, $identity_provider);
        $password = wp_generate_password(12, false);
        $user_id = wpmu_create_user(
            strtolower(Username::sanitize($scoped_username)),
            $password,
            sanitize_email($email)
        );

        if (!$user_id) {
            return self::errorRedirect(
                new \WP_Error('add_user_fail', __('Cannot add user.'))
            );
        }

        UserNotifications::sendNewUserAccount((int) $user_id);

        return self::userNewRedirect(array('update' => 'added'));
    }

    /**
     * Adds an existing network user to the current site.
     *
     * @return string Redirect URL after processing the request.
     */
    private static function handleExistingUserAddition(): string
    {
        check_admin_referer('add-user', '_wpnonce_add-user');
        self::requireCapability(
            'promote_users',
            __('Sorry, you are not allowed to add users to this site.')
        );

        $user_identifier = self::requestValue('email');
        $user = get_user_by('login', $user_identifier);

        if (!$user) {
            $user = get_user_by('email', $user_identifier);
        }

        if (!$user) {
            return self::userNewRedirect(array('update' => 'does_not_exist'));
        }

        $user_id = (int) $user->ID;
        $user_blogs = get_blogs_of_user($user_id);

        if (null !== $user->user_login && !is_super_admin($user_id) && array_key_exists(get_current_blog_id(), $user_blogs)) {
            return self::userNewRedirect(array('update' => 'addexisting'));
        }

        $role = sanitize_key(self::requestValue('role'));
        add_existing_user_to_blog(
            array(
                'user_id' => $user_id,
                'role' => $role,
            )
        );

        if (isset($_POST['noconfirmation']) && is_super_admin()) {
            return self::userNewRedirect(array('update' => 'addnoconfirmation'));
        }

        UserNotifications::sendExistingUserInvitation($user_id, $role);

        return self::userNewRedirect(array('update' => 'add'));
    }

    /**
     * Creates a user for either a Single Site or Multisite installation.
     *
     * @return string Redirect URL after processing the request.
     */
    private static function handleUserCreation(): string
    {
        check_admin_referer('create-user', '_wpnonce_create-user');
        self::requireCapability(
            'create_users',
            __('Sorry, you are not allowed to create users.')
        );

        if (is_multisite()) {
            return self::handleMultisiteUserCreation();
        }

        return self::handleSingleSiteUserCreation();
    }

    /**
     * Creates a user in a Single Site installation.
     *
     * @return string Redirect URL after processing the request.
     */
    private static function handleSingleSiteUserCreation(): string
    {
        $user_id = SingleSiteUserCreator::create();

        if (is_wp_error($user_id)) {
            return self::errorRedirect($user_id);
        }

        UserNotifications::sendNewUserAccount((int) $user_id);

        if (current_user_can('list_users')) {
            return add_query_arg(
                array(
                    'update' => 'add',
                    'id' => $user_id,
                ),
                'users.php'
            );
        }

        return self::userNewRedirect(array('update' => 'add'));
    }

    /**
     * Creates and immediately activates a user in a Multisite installation.
     *
     * @return string Redirect URL after processing the request.
     */
    private static function handleMultisiteUserCreation(): string
    {
        global $wpdb;

        $identity_provider = self::requestValue('user_idp');
        $username = self::requestValue('user_login');
        $email = self::requestValue('email');
        $role = sanitize_key(self::requestValue('role'));
        $user_details = UserSignupValidator::validate($identity_provider, $username, $email);

        if (self::hasValidationErrors($user_details)) {
            return self::errorRedirect($user_details['errors']);
        }

        $scoped_username = Username::addDomainScope($username, $identity_provider);
        $new_user_login = Username::sanitize($scoped_username);
        $new_user_email = sanitize_email($email);

        wpmu_signup_user(
            $new_user_login,
            $new_user_email,
            array(
                'add_to_blog' => $wpdb->blogid,
                'new_role' => $role,
            )
        );

        $activation_key = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s",
                $new_user_login,
                $new_user_email
            )
        );
        $signup = wpmu_activate_signup($activation_key);

        if (is_wp_error($signup)) {
            return self::errorRedirect($signup);
        }

        if (isset($_POST['noconfirmation']) && is_super_admin()) {
            return self::userNewRedirect(array('update' => 'addnoconfirmation'));
        }

        UserNotifications::sendSignupInvitation(
            (int) $signup['user_id'],
            $new_user_login,
            $new_user_email
        );

        return self::userNewRedirect(array('update' => 'newuserconfirmation'));
    }

    /**
     * Determines whether a validation result contains errors.
     *
     * @param array $user_details Signup validation result.
     * @return bool Whether validation errors are present.
     */
    private static function hasValidationErrors(array $user_details): bool
    {
        return isset($user_details['errors'])
            && is_wp_error($user_details['errors'])
            && $user_details['errors']->has_errors();
    }

    /**
     * Builds the add-user page URL with optional query arguments.
     *
     * @param array $arguments Additional query arguments.
     * @return string Add-user page URL.
     */
    private static function userNewRedirect(array $arguments = array()): string
    {
        return add_query_arg(
            array_merge(array('page' => 'usernew'), $arguments),
            'users.php'
        );
    }

    /**
     * Builds an add-user page URL containing serialized validation errors.
     *
     * @param \WP_Error $errors    Errors to pass to the add-user page.
     * @param array     $arguments Additional query arguments.
     * @return string Add-user page URL.
     */
    private static function errorRedirect(\WP_Error $errors, array $arguments = array()): string
    {
        $arguments['error'] = base64_encode(serialize($errors));

        return self::userNewRedirect($arguments);
    }

    /**
     * Stops request processing when the current user lacks a capability.
     *
     * @param string $capability Required WordPress capability.
     * @param string $message    Error message shown to the current user.
     * @return void
     */
    private static function requireCapability(string $capability, string $message): void
    {
        if (!current_user_can($capability)) {
            wp_die($message, 403);
            exit;
        }
    }

    /**
     * Returns a scalar request value as an unslashed string.
     *
     * @param string $key Request key.
     * @return string Request value or an empty string.
     */
    private static function requestValue(string $key): string
    {
        $value = $_REQUEST[$key] ?? '';

        return is_scalar($value) ? (string) wp_unslash($value) : '';
    }

    /**
     * Returns a scalar array value as a string.
     *
     * @param array  $values Source values.
     * @param string $key    Array key.
     * @return string Array value or an empty string.
     */
    private static function arrayStringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
