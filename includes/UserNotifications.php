<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Sends email notifications related to user creation and site invitations.
 */
class UserNotifications
{
    /**
     * Sends an invitation to an existing user who was added to a site.
     *
     * @param int    $user_id User ID.
     * @param string $role    Assigned role key.
     * @return void
     */
    public static function sendExistingUserInvitation(int $user_id, string $role): void
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return;
        }

        $blog_name = self::blogName();
        $roles = get_editable_roles();
        $role_name = $roles[$role]['name'] ?? $role;
        $message = sprintf(
            /* translators: 1: Blog name, 2: Home URL, 3: User role, 4: Login URL, 5: EOL. */
            __('Hi,%5$s%5$sYou\'ve been invited to join \'%1$s\' at %2$s with the role of %3$s.%5$s%5$sPlease sign in using the following link to the website:%5$s%4$s', 'rrze-sso'),
            $blog_name,
            home_url(),
            wp_specialchars_decode(translate_user_role($role_name)),
            wp_login_url(),
            PHP_EOL
        );

        wp_mail(
            $user->user_email,
            sprintf(
                /* translators: %s: Blog name. */
                __("[%s] You've been invited", 'rrze-sso'),
                $blog_name
            ),
            $message
        );
    }

    /**
     * Sends an account-created notification using persisted user data.
     *
     * @param int $user_id User ID.
     * @return void
     * @throws \Exception If a secure temporary password cannot be generated.
     */
    public static function sendNewUserAccount(int $user_id): void
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return;
        }

        self::sendAccountCreated($user_id, $user->user_login, $user->user_email);
    }

    /**
     * Sends an account-created notification after activating a Multisite signup.
     *
     * @param int    $user_id User ID.
     * @param string $login   User login.
     * @param string $email   User email address.
     * @return void
     * @throws \Exception If a secure temporary password cannot be generated.
     */
    public static function sendSignupInvitation(int $user_id, string $login, string $email): void
    {
        self::sendAccountCreated($user_id, $login, $email);
    }

    /**
     * Resets the generated password and sends the shared account-created email.
     *
     * @param int    $user_id User ID.
     * @param string $login   User login.
     * @param string $email   User email address.
     * @return void
     * @throws \Exception If a secure temporary password cannot be generated.
     */
    private static function sendAccountCreated(int $user_id, string $login, string $email): void
    {
        $password = bin2hex(random_bytes(4));
        wp_set_password($password, $user_id);

        $blog_name = self::blogName();
        $format = __(
            /* translators: 1: User name, 2: Blog name, 3: Login URL, 4: EOL. */
            'Hi,%4$s%4$sYour user account %1$s has been created.%4$sPlease sign in using the following link to the website:%4$s%3$s%4$s',
            'rrze-sso'
        );
        $format .= __(
            /* translators: 2: Blog name, 4: EOL. */
            '%4$sThanks!%4$s%4$s--The Team @ %2$s',
            'rrze-sso'
        );
        $message = sprintf($format, $login, $blog_name, wp_login_url(), PHP_EOL);

        wp_mail(
            $email,
            sprintf(
                /* translators: %s: Blog name. */
                __('[%s] Your user account', 'rrze-sso'),
                $blog_name
            ),
            $message
        );
    }

    /**
     * Returns the decoded site name used in email messages.
     *
     * @return string Site name.
     */
    private static function blogName(): string
    {
        return wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
    }
}
