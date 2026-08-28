<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\Username;

/**
 * Tests username normalization and validation independently of WordPress.
 */
#[CoversClass(Username::class)]
class UsernameTest extends TestCase
{
    /**
     * Provides the WordPress functions used by the username helper.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('wp_strip_all_tags')->alias(
            static fn(string $value): string => strip_tags($value)
        );
        Functions\when('remove_accents')->returnArg();
    }

    /**
     * Ensures markup, encoded values, and surrounding whitespace are removed.
     *
     * @return void
     */
    public function testSanitizeNormalizesUsername(): void
    {
        self::assertSame(
            'Alice',
            Username::sanitize(' <strong>Alice</strong>%20 &amp; ', true)
        );
    }

    /**
     * Ensures scoped-login characters remain available in strict mode.
     *
     * @return void
     */
    public function testSanitizeRetainsDomainScope(): void
    {
        self::assertSame(
            'alice@example.org',
            Username::sanitize('alice@example.org', true)
        );
    }

    /**
     * Ensures username patterns return a simple boolean result.
     *
     * @return void
     */
    public function testMatchesPattern(): void
    {
        self::assertTrue(Username::matchesPattern('alice42', '/^[a-z0-9]+$/'));
        self::assertFalse(Username::matchesPattern('alice-42', '/^[a-z0-9]+$/'));
    }

    public function testAddDomainScopeUsesProviderConfiguration(): void
    {
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->justReturn(array(
            'domain_scope' => array('idp-one' => 'example.org'),
        ));
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );

        self::assertSame('alice@example.org', Username::addDomainScope('alice', 'idp-one'));
        self::assertSame('alice', Username::addDomainScope('alice', 'unknown'));
    }
}
