<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\UserNotifications;

#[CoversClass(UserNotifications::class)]
class UserNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('__')->returnArg();
        Functions\when('get_option')->justReturn('Example &amp; Site');
        Functions\when('wp_specialchars_decode')->alias(
            static fn(string $value): string => html_entity_decode($value, ENT_QUOTES)
        );
        Functions\when('wp_login_url')->justReturn('https://example.org/wp-login.php');
    }

    public function testSendNewUserAccountResetsPasswordAndSendsMail(): void
    {
        $user = (object) array(
            'user_login' => 'alice@example.org',
            'user_email' => 'alice@example.org',
        );
        $mail = array();
        $passwordReset = array();

        Functions\when('get_userdata')->justReturn($user);
        Functions\when('wp_set_password')->alias(
            function (string $password, int $userId) use (&$passwordReset): void {
                $passwordReset = compact('password', 'userId');
            }
        );
        Functions\when('wp_mail')->alias(
            function (string $to, string $subject, string $message) use (&$mail): bool {
                $mail = compact('to', 'subject', 'message');
                return true;
            }
        );

        UserNotifications::sendNewUserAccount(7);

        self::assertSame(7, $passwordReset['userId']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $passwordReset['password']);
        self::assertSame('alice@example.org', $mail['to']);
        self::assertSame('[Example & Site] Your user account', $mail['subject']);
        self::assertStringContainsString('alice@example.org', $mail['message']);
        self::assertStringContainsString('https://example.org/wp-login.php', $mail['message']);
    }

    public function testExistingUserInvitationIncludesRoleAndSiteDetails(): void
    {
        $user = (object) array('user_email' => 'bob@example.org');
        $mail = array();

        Functions\when('get_userdata')->justReturn($user);
        Functions\when('get_editable_roles')->justReturn(array('editor' => array('name' => 'Editor')));
        Functions\when('translate_user_role')->returnArg();
        Functions\when('home_url')->justReturn('https://example.org');
        Functions\when('wp_mail')->alias(
            function (string $to, string $subject, string $message) use (&$mail): bool {
                $mail = compact('to', 'subject', 'message');
                return true;
            }
        );

        UserNotifications::sendExistingUserInvitation(9, 'editor');

        self::assertSame('bob@example.org', $mail['to']);
        self::assertStringContainsString('Editor', $mail['message']);
        self::assertStringContainsString('Example & Site', $mail['subject']);
    }

    public function testMissingUserDoesNotSendNotification(): void
    {
        Functions\when('get_userdata')->justReturn(false);
        Functions\expect('wp_mail')->never();

        UserNotifications::sendNewUserAccount(404);
    }

    public function testMissingExistingUserDoesNotSendInvitation(): void
    {
        Functions\when('get_userdata')->justReturn(false);
        Functions\expect('wp_mail')->never();

        UserNotifications::sendExistingUserInvitation(404, 'subscriber');
    }

    public function testSignupInvitationUsesSubmittedAccountDetails(): void
    {
        $mailTo = '';
        Functions\when('wp_set_password')->justReturn(null);
        Functions\when('wp_mail')->alias(
            function (string $to) use (&$mailTo): bool {
                $mailTo = $to;
                return true;
            }
        );

        UserNotifications::sendSignupInvitation(12, 'signup@example.org', 'signup@example.org');

        self::assertSame('signup@example.org', $mailTo);
    }
}
