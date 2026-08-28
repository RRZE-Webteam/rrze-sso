<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\Users;

#[CoversClass(Users::class)]
class UsersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('wp_strip_all_tags')->alias(static fn(string $value): string => strip_tags($value));
        Functions\when('remove_accents')->returnArg();
    }

    public function testCompatibilityMethodsDelegateUsernameBehavior(): void
    {
        self::assertSame('Alice@example.org', Users::sanitizeUserName(' <b>Alice@example.org</b> ', true));
        self::assertSame('Alice+Admin@example.org', Users::sanitizeUserName(' Alice+Admin@example.org '));
        self::assertSame('AliceAdmin@example.org', Users::sanitizeUserName(' Alice+Admin@example.org ', true));
        self::assertTrue(Users::isValidUsername('/^[a-z]+$/', 'alice'));
        self::assertFalse(Users::isValidUsername('/^[a-z]+$/', 'alice42'));
    }

    public function testActivateSignupRejectsNonScalarKeys(): void
    {
        self::assertFalse(Users::activateSignup(array('not-a-key')));
    }

    public function testActivateSignupDelegatesScalarKeysToTheSignupLookup(): void
    {
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $wpdb = new UsersActivationWpdb();
        $GLOBALS['wpdb'] = $wpdb;

        try {
            self::assertFalse(Users::activateSignup('missing-key'));
            self::assertSame(array('missing-key'), $wpdb->preparedArguments);
        } finally {
            if (null === $previousWpdb) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    }

    public function testUserNewActionIgnoresUnrelatedRequest(): void
    {
        $previousRequest = $_REQUEST;
        $_REQUEST = array();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_key')->returnArg();

        Users::userNewAction();

        $_REQUEST = $previousRequest;
        self::assertTrue(true);
    }
}

class UsersActivationWpdb
{
    /** @var string */
    public $signups = 'wp_signups';

    /** @var array<int, string> */
    public $preparedArguments = array();

    public function prepare(string $query, ...$arguments): string
    {
        $this->preparedArguments = $arguments;

        return $query;
    }

    public function get_row(string $query): ?object
    {
        return null;
    }
}
