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
        global $wpdb;

        if (empty($key)) {
            return false;
        }

        $signup = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->signups} WHERE activation_key = %s",
                $key
            )
        );

        if (empty($signup) || $signup->active) {
            return false;
        }

        $meta = maybe_unserialize($signup->meta);
        $password = wp_generate_password(12, false);
        $user_id = username_exists($signup->user_login);
        $user_already_exists = (bool) $user_id;

        if (!$user_id) {
            $user_id = wpmu_create_user($signup->user_login, $password, $signup->user_email);
        }

        if (!$user_id) {
            return false;
        }

        $activated_at = current_time('mysql', true);

        if (empty($signup->domain)) {
            self::markSignupActive($key, $activated_at);

            if ($user_already_exists) {
                return false;
            }

            wpmu_welcome_user_notification($user_id, $password, $meta);
            do_action('wpmu_activate_user', $user_id, $password, $meta);

            return true;
        }

        $blog_id = wpmu_create_blog(
            $signup->domain,
            $signup->path,
            $signup->title,
            $user_id,
            $meta,
            $wpdb->siteid
        );

        if (is_wp_error($blog_id)) {
            if ('blog_taken' === $blog_id->get_error_code()) {
                self::markSignupActive($key, $activated_at);
            }

            return false;
        }

        self::markSignupActive($key, $activated_at);
        wpmu_welcome_notification($blog_id, $user_id, $password, $signup->title, $meta);
        do_action('wpmu_activate_blog', $blog_id, $user_id, $password, $signup->title, $meta);

        return true;
    }

    /**
     * Marks a signup record as active.
     *
     * @param string $key          Signup activation key.
     * @param string $activated_at GMT activation timestamp.
     * @return void
     */
    private static function markSignupActive(string $key, string $activated_at): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->signups,
            array(
                'active' => 1,
                'activated' => $activated_at,
            ),
            array('activation_key' => $key)
        );
    }
}
