<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Validates SSO-aware user signup data.
 */
class UserSignupValidator
{
    /**
     * Validates an identity provider, username, and email address.
     *
     * @param string $identity_provider Identity-provider key.
     * @param string $username          Requested username.
     * @param string $email             Requested email address.
     * @return array{user_name: string, orig_username: string, user_email: string, errors: \WP_Error} Signup data and collected errors.
     */
    public static function validate(string $identity_provider, string $username, string $email): array
    {
        global $wpdb;

        $errors = new \WP_Error();
        $identity_provider_exists = self::validateIdentityProvider($identity_provider, $errors);
        $options = Options::getOptions();
        $original_username = $username;
        $username = self::validateUsername($username, $identity_provider, $options, $errors);
        $email = self::validateEmail($email, $options, $errors);

        if ($identity_provider_exists) {
            $username .= '@' . $identity_provider;
        }

        if (username_exists($username)) {
            $errors->add('user_name', __('Sorry, that username already exists!'));
        }

        if (email_exists($email)) {
            $errors->add('user_email', __('Sorry, that email address is already used!'));
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->signups} WHERE user_login = %s OR user_email = %s",
                $username,
                $email
            )
        );

        $result = array(
            'user_name' => $username,
            'orig_username' => $original_username,
            'user_email' => $email,
            'errors' => $errors,
        );

        return apply_filters('wpmu_validate_user_signup', $result);
    }

    /**
     * Validates that the requested identity provider exists.
     *
     * @param string    $identity_provider Identity-provider key.
     * @param \WP_Error $errors             Error collection.
     * @return bool Whether the identity provider exists.
     */
    private static function validateIdentityProvider(string $identity_provider, \WP_Error $errors): bool
    {
        if ('' === $identity_provider) {
            $errors->add('user_idp', __('Please select an identity provider.', 'rrze-sso'));
            return false;
        }

        foreach (array_keys(simpleSAML()->getIdentityProviders()) as $key) {
            if ($identity_provider === sanitize_title($key)) {
                return true;
            }
        }

        $errors->add('user_idp', __('Sorry, that identity provider does not exist!', 'rrze-sso'));

        return false;
    }

    /**
     * Validates and normalizes a requested username.
     *
     * @param string    $username          Requested username.
     * @param string    $identity_provider Identity-provider key.
     * @param object    $options           Plugin options.
     * @param \WP_Error $errors             Error collection.
     * @return string Validated username.
     */
    private static function validateUsername(string $username, string $identity_provider, object $options, \WP_Error $errors): string
    {
        $original_username = $username;
        $username = Username::sanitize($username);
        $pattern = $options->username_regex_pattern ?: '/^[a-z0-9]+$/i';
        $pattern_error = $options->username_regex_pattern
            ? __('Apologies, but that username is not permitted.', 'rrze-sso')
            : __('Invalid username. Usernames may only contain letters and numbers.', 'rrze-sso');

        if ($username !== $original_username || !Username::matchesPattern($username, $pattern)) {
            $errors->add('user_name', $pattern_error);
            $username = $original_username;
        }

        if ('' === $username) {
            $errors->add('user_name', __('Please enter a username.'));
        }

        $illegal_names = get_site_option('illegal_names');
        if (!is_array($illegal_names)) {
            $illegal_names = array('www', 'web', 'root', 'admin', 'main', 'invite', 'administrator');
            add_site_option('illegal_names', $illegal_names);
        }

        if (in_array($username, $illegal_names, true)) {
            $errors->add('user_name', __('Sorry, that username is not allowed.'));
        }

        if (strlen($username) < 4) {
            $errors->add('user_name', __('The username must be at least 4 characters.'));
        }

        $domain_scope = $options->domain_scope[$identity_provider] ?? '';
        $maximum_length = 60 - ($domain_scope ? strlen($domain_scope) + 1 : 0);

        if (strlen($username) > $maximum_length) {
            $errors->add(
                'user_name',
                sprintf(
                    /* translators: %s: Max length of username. */
                    __('Username may not be longer than %s characters.', 'rrze-sso'),
                    $maximum_length
                )
            );
        }

        if (preg_match('/^[0-9]*$/', $username)) {
            $errors->add('user_name', __('Sorry, usernames must have letters too!'));
        }

        return $username;
    }

    /**
     * Validates and normalizes a requested email address.
     *
     * @param string    $email   Requested email address.
     * @param object    $options Plugin options.
     * @param \WP_Error $errors  Error collection.
     * @return string Sanitized email address.
     */
    private static function validateEmail(string $email, object $options, \WP_Error $errors): string
    {
        $email = sanitize_email($email);

        if (is_email_address_unsafe($email)) {
            $errors->add('user_email', __('Sorry, that email address is not allowed!'));
        }

        if (!is_email($email)) {
            $errors->add('user_email', __('Please enter a valid email address.'));
        }

        $email_domain = self::emailDomain($email);
        $limited_domains = get_site_option('limited_email_domains');

        if (is_array($limited_domains) && !empty($limited_domains) && !in_array($email_domain, $limited_domains, true)) {
            $errors->add('user_email', __('Sorry, that email address is not allowed!'));
        }

        $allowed_domains = $options->allowed_user_email_domains;
        if (is_array($allowed_domains) && !empty($allowed_domains) && !in_array($email_domain, $allowed_domains, true)) {
            $errors->add(
                'user_email',
                sprintf(
                    /* translators: %s: List of allowed domains. */
                    __('Sorry, that email address domain is not allowed! Allowed domains: %s', 'rrze-sso'),
                    implode(', ', $allowed_domains)
                )
            );
        }

        return $email;
    }

    /**
     * Extracts the domain from an email address.
     *
     * @param string $email Email address.
     * @return string Email domain or an empty string.
     */
    private static function emailDomain(string $email): string
    {
        $separator_position = strrpos($email, '@');

        return false === $separator_position ? '' : substr($email, $separator_position + 1);
    }
}
