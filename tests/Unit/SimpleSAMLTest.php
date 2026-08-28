<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;
use SimpleSAML\Auth\Simple as AuthClient;
use WP_Error;

#[CoversClass(SimpleSAML::class)]
class SimpleSAMLTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        defined('WP_CONTENT_DIR') || define('WP_CONTENT_DIR', ABSPATH);
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->justReturn(array(
            'simplesaml_include' => '/missing-simple-saml-autoload.php',
            'simplesaml_auth_source' => 'test-sp',
        ));
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('__')->returnArg();
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
    }

    public function testLoadedReportsMissingSimpleSamlLibrary(): void
    {
        $service = new SimpleSAML();

        self::assertFalse($service->loaded());
        self::assertNull($service->getAuthSimple());
        self::assertSame(array(), $service->getIdentityProviders());
    }

    public function testProtectedInitializersBuildClientAndLocalizedProviders(): void
    {
        Functions\when('get_locale')->justReturn('de_DE');
        $service = new TestableSimpleSAML();

        $service->initializeAuthClient();
        $service->initializeIdentityProviders();

        self::assertInstanceOf(AuthClient::class, $service->getAuthSimple());
        self::assertSame(
            array(
                'https://idp-one.example.org' => 'Deutscher Name',
                'https://idp-two.example.org' => 'Static Name',
                'https://idp-three.example.org' => 'idp-three.example.org',
            ),
            $service->getIdentityProviders()
        );
    }
}

class TestableSimpleSAML extends SimpleSAML
{
    public function initializeAuthClient(): void
    {
        $this->setAuthSimple();
    }

    public function initializeIdentityProviders(): void
    {
        $this->setIdentityProviders();
    }

    public function identityProviders(): array
    {
        return array(
            'https://idp-one.example.org' => array('name' => array('de' => 'Deutscher Name')),
            'https://idp-two.example.org' => array('name' => 'Static Name'),
            'https://idp-three.example.org' => array(),
        );
    }
}
