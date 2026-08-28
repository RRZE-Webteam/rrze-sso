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
        self::assertTrue(Users::isValidUsername('/^[a-z]+$/', 'alice'));
        self::assertFalse(Users::isValidUsername('/^[a-z]+$/', 'alice42'));
    }

    public function testActivateSignupRejectsNonScalarKeys(): void
    {
        self::assertFalse(Users::activateSignup(array('not-a-key')));
    }
}
