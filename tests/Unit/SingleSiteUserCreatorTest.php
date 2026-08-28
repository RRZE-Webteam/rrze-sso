<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\SingleSiteUserCreator;
use RRZE\SSO\Tests\TestCase;
use WP_Error;

#[CoversClass(SingleSiteUserCreator::class)]
class SingleSiteUserCreatorTest extends TestCase
{
    /** @var array<string, mixed> */
    private $previousPost = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousPost = $_POST;
        $_POST = array();

        Functions\when('__')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('wp_strip_all_tags')->alias(static fn(string $value): string => strip_tags($value));
        Functions\when('remove_accents')->returnArg();
        Functions\when('sanitize_text_field')->alias(static fn(string $value): string => trim(strip_tags($value)));
        Functions\when('wp_get_user_contact_methods')->justReturn(array());
        Functions\when('validate_username')->alias(
            static fn(string $username): bool => 1 === preg_match('/^[a-z0-9.\-@]+$/i', $username)
        );
        Functions\when('username_exists')->justReturn(false);
        Functions\when('email_exists')->justReturn(false);
        Functions\when('is_email')->alias(
            static fn(string $email): bool => false !== filter_var($email, FILTER_VALIDATE_EMAIL)
        );
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->justReturn(array('domain_scope' => array('idp' => 'example.org')));
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
    }

    protected function tearDown(): void
    {
        $_POST = $this->previousPost;
        parent::tearDown();
    }

    public function testCreateBuildsScopedUserAndInsertsIt(): void
    {
        $_POST = array(
            'user_idp' => 'idp',
            'user_login' => 'alice',
            'email' => ' alice@example.org ',
            'use_ssl' => '1',
        );

        Functions\expect('wp_insert_user')
            ->once()
            ->withArgs(
                static fn(object $user): bool => 'alice@example.org' === $user->user_login
                    && 'alice@example.org' === $user->user_email
                    && 1 === $user->use_ssl
            )
            ->andReturn(42);

        self::assertSame(42, SingleSiteUserCreator::create());
    }

    public function testCreateReturnsAllRequiredFieldErrors(): void
    {
        $result = SingleSiteUserCreator::create();

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertContains('user_login', $result->get_error_codes());
        self::assertContains('empty_email', $result->get_error_codes());
    }
}
