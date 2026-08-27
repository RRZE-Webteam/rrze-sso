<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Activates pending WordPress Multisite signups.
 */
class SignupActivator
{
    /**
     * Activates a user or site signup by activation key.
     *
     * @param string $key Signup activation key.
     * @return bool Whether the signup was activated successfully.
     */
    public static function activate($key): bool
    {
        if (!is_scalar($key) || empty($key)) {
            return false;
        }

        $key = (string) $key;
        $signup = self::findPendingSignup($key);
        if (!$signup) {
            return false;
        }

        $meta = maybe_unserialize($signup->meta);
        $password = wp_generate_password(12, false);
        $user = self::resolveUser($signup, $password);
        if (!$user) {
            return false;
        }

        if (empty($signup->domain)) {
            return self::activateUserSignup($key, $user, $password, $meta);
        }

        return self::activateSiteSignup($signup, $key, $user['id'], $password, $meta);
    }

    /**
     * Finds an inactive signup by its activation key.
     *
     * @param string $key Signup activation key.
     * @return object|null Pending signup record, or null when none exists.
     */
    private static function findPendingSignup(string $key): ?object
    {
        global $wpdb;

        $signup = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->signups} WHERE activation_key = %s",
                $key
            )
        );

        return $signup && !$signup->active ? $signup : null;
    }

    /**
     * Finds the signup user or creates a new account for them.
     *
     * @param object $signup   Pending signup record.
     * @param string $password Generated account password.
     * @return array{id: int, already_exists: bool}|null Resolved user data, or null on failure.
     */
    private static function resolveUser(object $signup, string $password): ?array
    {
        $userId = username_exists($signup->user_login);
        $alreadyExists = (bool) $userId;

        if (!$userId) {
            $userId = wpmu_create_user(
                $signup->user_login,
                $password,
                $signup->user_email
            );
        }

        if (!$userId) {
            return null;
        }

        return array(
            'id' => (int) $userId,
            'already_exists' => $alreadyExists,
        );
    }

    /**
     * Completes a signup that creates only a user account.
     *
     * Existing-user signups are marked active but retain the historical false
     * return value and do not trigger welcome notifications.
     *
     * @param string                                $key      Signup activation key.
     * @param array{id: int, already_exists: bool}  $user     Resolved user data.
     * @param string                                $password Generated account password.
     * @param mixed                                 $meta     Unserialized signup metadata.
     * @return bool Whether a new user was activated.
     */
    private static function activateUserSignup(
        string $key,
        array $user,
        string $password,
        $meta
    ): bool {
        self::markSignupActive($key);

        if ($user['already_exists']) {
            return false;
        }

        wpmu_welcome_user_notification($user['id'], $password, $meta);
        do_action('wpmu_activate_user', $user['id'], $password, $meta);

        return true;
    }

    /**
     * Creates the site associated with a signup and completes its activation.
     *
     * @param object $signup   Pending signup record.
     * @param string $key      Signup activation key.
     * @param int    $userId   Signup owner user ID.
     * @param string $password Generated account password.
     * @param mixed  $meta     Unserialized signup metadata.
     * @return bool Whether the site signup was activated.
     */
    private static function activateSiteSignup(
        object $signup,
        string $key,
        int $userId,
        string $password,
        $meta
    ): bool {
        global $wpdb;

        $blogId = wpmu_create_blog(
            $signup->domain,
            $signup->path,
            $signup->title,
            $userId,
            $meta,
            $wpdb->siteid
        );

        if (is_wp_error($blogId)) {
            if ('blog_taken' === $blogId->get_error_code()) {
                self::markSignupActive($key);
            }

            return false;
        }

        self::markSignupActive($key);
        wpmu_welcome_notification($blogId, $userId, $password, $signup->title, $meta);
        do_action('wpmu_activate_blog', $blogId, $userId, $password, $signup->title, $meta);

        return true;
    }

    /**
     * Marks a signup record as active.
     *
     * @param string $key Signup activation key.
     * @return void
     */
    private static function markSignupActive(string $key): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->signups,
            array(
                'active' => 1,
                'activated' => current_time('mysql', true),
            ),
            array('activation_key' => $key)
        );
    }
}
