<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Provides the public user-management API used by the plugin.
 *
 * Keeps the existing static entry points stable while delegating registration,
 * validation, notification, and activation work to focused classes.
 */
class Users
{
    /**
     * Handles submissions from the custom add-user administration pages.
     *
     * @return void
     */
    public static function userNewAction(): void
    {
        UserRegistrationHandler::handle();
    }

    /**
     * Activates a pending Multisite signup.
     *
     * @param string $key Signup activation key.
     * @return bool Whether the signup was activated successfully.
     */
    public static function activateSignup($key): bool
    {
        return SignupActivator::activate($key);
    }

    /**
     * Sanitizes a username while retaining characters used by scoped logins.
     *
     * @param string $username Username to sanitize.
     * @param bool   $strict   Whether to restrict the username to portable ASCII characters.
     * @return string Sanitized username.
     */
    public static function sanitizeUserName($username, $strict = false): string
    {
        return Username::sanitize($username, (bool) $strict);
    }

    /**
     * Checks whether a username matches a configured regular expression.
     *
     * @param string $pattern  Regular expression used for validation.
     * @param string $username Username to validate.
     * @return bool Whether the username matches the expression.
     */
    public static function isValidUsername(string $pattern, string $username): bool
    {
        return Username::matchesPattern($username, $pattern);
    }
}
