<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Provides username normalization, scoping, and pattern matching.
 */
class Username
{
    /**
     * Sanitizes a username while retaining characters used by scoped logins.
     *
     * @param string $username Username to sanitize.
     * @param bool   $strict   Whether to restrict the username to portable ASCII characters.
     * @return string Sanitized username.
     */
    public static function sanitize($username, bool $strict = false): string
    {
        $username = wp_strip_all_tags((string) $username);
        $username = remove_accents($username);

        // Remove percent-encoded characters and HTML entities.
        $username = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '', $username) ?? '';
        $username = preg_replace('/&.+?;/', '', $username) ?? '';

        if ($strict) {
            $username = preg_replace('|[^a-z0-9 _.\-@]|i', '', $username) ?? '';
        }

        $username = trim($username);

        return preg_replace('|\s+|', ' ', $username) ?? '';
    }

    /**
     * Adds the configured domain scope for an identity provider to a username.
     *
     * @param string $username          Base username.
     * @param string $identity_provider Identity-provider key.
     * @return string Scoped username.
     */
    public static function addDomainScope(string $username, string $identity_provider): string
    {
        $options = Options::getOptions();
        $domain_scope = $options->domain_scope[$identity_provider] ?? '';

        return $username . ($domain_scope ? '@' . $domain_scope : '');
    }

    /**
     * Checks whether a username matches a regular expression.
     *
     * @param string $username Username to validate.
     * @param string $pattern  Regular expression used for validation.
     * @return bool Whether the username matches the expression.
     */
    public static function matchesPattern(string $username, string $pattern): bool
    {
        return (bool) preg_match($pattern, $username);
    }
}
