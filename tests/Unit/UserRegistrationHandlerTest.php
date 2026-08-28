<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\UserRegistrationHandler;
use RuntimeException;

#[CoversClass(UserRegistrationHandler::class)]
class UserRegistrationHandlerTest extends TestCase
{
    /** @var array<string, mixed> */
    private $previousRequest = array();

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousRequest = $_REQUEST;
        $_REQUEST = array();

        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_key')->alias(
            static fn(string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->previousRequest;
        parent::tearDown();
    }

    public function testHandleIgnoresUnsupportedActions(): void
    {
        $_REQUEST['action'] = array('invalid');
        Functions\expect('wp_redirect')->never();

        UserRegistrationHandler::handle();

        self::assertTrue(true);
    }

    public function testExistingUserActionRedirectsWhenAccountCannotBeFound(): void
    {
        $_REQUEST = array(
            'action' => '_admin_add-user',
            'email' => 'missing@example.org',
        );
        Functions\when('__')->returnArg();
        Functions\when('check_admin_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('get_user_by')->twice()->andReturn(false);
        Functions\when('add_query_arg')->alias(
            static fn(array $arguments, string $url): string => $url . '?' . http_build_query($arguments)
        );
        Functions\when('wp_redirect')->alias(
            static function (string $url): void {
                throw new RegistrationRedirected($url);
            }
        );

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=does_not_exist');

        UserRegistrationHandler::handle();
    }
}

class RegistrationRedirected extends RuntimeException
{
}
