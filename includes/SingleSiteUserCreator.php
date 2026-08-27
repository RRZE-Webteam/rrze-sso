<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Builds, validates, and inserts users in a Single Site installation.
 */
class SingleSiteUserCreator
{
    /**
     * Creates a user from the custom add-user form.
     *
     * @return int|\WP_Error Created user ID or validation error.
     */
    public static function create(): int|\WP_Error
    {
        $user = self::buildUser();
        $errors = self::validateUser($user);

        if ($errors->has_errors()) {
            return $errors;
        }

        return wp_insert_user($user);
    }

    /**
     * Builds a user object from the submitted form values.
     *
     * @return \stdClass User data ready for validation and insertion.
     */
    private static function buildUser(): \stdClass
    {
        global $wp_roles;

        $identity_provider = self::postValue('user_idp');
        $username = self::postValue('user_login');
        $user = (object) array(
            'user_login' => '',
            'user_email' => '',
            'comment_shortcuts' => '',
            'use_ssl' => !empty($_POST['use_ssl']) ? 1 : 0,
        );

        if ('' !== $username) {
            $user->user_login = Username::sanitize(
                Username::addDomainScope($username, $identity_provider)
            );
        }

        if (isset($_POST['role']) && current_user_can('edit_users')) {
            $new_role = sanitize_key(self::postValue('role'));
            $potential_role = $wp_roles->role_objects[$new_role] ?? false;

            if ((is_multisite() && current_user_can('manage_sites')) || ($potential_role && $potential_role->has_cap('edit_users'))) {
                $user->role = $new_role;
            }

            $editable_roles = get_editable_roles();
            if ('' !== $new_role && empty($editable_roles[$new_role])) {
                wp_die(__('Sorry, you are not allowed to give users that role.'), 403);
            }
        }

        if (isset($_POST['email'])) {
            $user->user_email = sanitize_text_field(self::postValue('email'));
        }

        foreach (wp_get_user_contact_methods($user) as $method => $name) {
            if (isset($_POST[$method])) {
                $user->{$method} = sanitize_text_field(self::postValue($method));
            }
        }

        return $user;
    }

    /**
     * Validates a user built from the submitted form values.
     *
     * @param \stdClass $user User data to validate.
     * @return \WP_Error Collected validation errors.
     */
    private static function validateUser(\stdClass $user): \WP_Error
    {
        $errors = new \WP_Error();
        $submitted_username = self::postValue('user_login');

        if ('' === $user->user_login) {
            $errors->add('user_login', __('<strong>ERROR</strong>: Please enter a username.', 'rrze-sso'));
        }

        if ('' !== $submitted_username && !validate_username($submitted_username)) {
            $errors->add('user_login', __('<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.', 'rrze-sso'));
        }

        if (username_exists($user->user_login)) {
            $errors->add('user_login', __('<strong>ERROR</strong>: This username is already registered. Please choose another one.', 'rrze-sso'));
        }

        if (empty($user->user_email)) {
            $errors->add('empty_email', __('<strong>ERROR</strong>: Please enter an email address.', 'rrze-sso'), array('form-field' => 'email'));
        } elseif (!is_email($user->user_email)) {
            $errors->add('invalid_email', __('<strong>ERROR</strong>: The email address isn\'t correct.', 'rrze-sso'), array('form-field' => 'email'));
        } elseif (email_exists($user->user_email)) {
            $errors->add('email_exists', __('<strong>ERROR</strong>: This email address is already registered, please choose another one.', 'rrze-sso'), array('form-field' => 'email'));
        }

        return $errors;
    }

    /**
     * Returns a scalar POST value as an unslashed string.
     *
     * @param string $key POST key.
     * @return string POST value or an empty string.
     */
    private static function postValue(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_scalar($value) ? (string) wp_unslash($value) : '';
    }
}
