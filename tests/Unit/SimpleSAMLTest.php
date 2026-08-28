<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Plugin;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;
use SimpleSAML\Auth\Simple as AuthClient;
use SimpleSAML\Configuration;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use WP_Error;

#[CoversClass(SimpleSAML::class)]
class SimpleSAMLTest extends TestCase
{
    /** @var array<string, mixed> */
    private $options = array();

    protected function setUp(): void
    {
        parent::setUp();

        defined('WP_CONTENT_DIR') || define('WP_CONTENT_DIR', ABSPATH);
        $this->options = array(
            'simplesaml_include' => '/missing-simple-saml-autoload.php',
            'simplesaml_auth_source' => 'test-sp',
        );
        AuthClient::$constructorException = null;
        MetaDataStorageHandler::reset();
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->alias(fn(): array => $this->options);
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('__')->returnArg();
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\when('get_locale')->justReturn('en_US');
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

    public function testLoadedInitializesIntegrationWhenAutoloadFileExists(): void
    {
        $this->options['simplesaml_include'] = '/tests/stubs/simplesamlphp.php';
        $service = new SimpleSAML();

        self::assertTrue($service->loaded());
        self::assertInstanceOf(AuthClient::class, $service->getAuthSimple());
        self::assertSame(array(), $service->getIdentityProviders());
    }

    public function testIdentityProvidersReturnsOnlyAvailableMetadata(): void
    {
        MetaDataStorageHandler::$entityList = array(
            'https://idp-one.example.org' => true,
            'https://idp-two.example.org' => true,
        );
        MetaDataStorageHandler::$metadata = array(
            'https://idp-one.example.org' => new Configuration(array('name' => 'Provider One')),
            'https://idp-two.example.org' => null,
        );

        self::assertSame(
            array('https://idp-one.example.org' => array('name' => 'Provider One')),
            (new SimpleSAML())->identityProviders()
        );
    }

    public function testAuthenticationClientFailureIsReported(): void
    {
        AuthClient::$constructorException = new \RuntimeException('Invalid authentication source');
        $service = new TestableSimpleSAML();

        $service->initializeAuthClient();

        self::assertNull($service->getAuthSimple());
        AuthClient::$constructorException = null;
    }

    public function testMissingLibraryNoticeCanBeRenderedForAdministrators(): void
    {
        $actions = array();
        $plugin = Mockery::mock(Plugin::class);
        $plugin->shouldReceive('getFile')->once()->andReturn('/plugins/rrze-sso/rrze-sso.php');
        $plugin->shouldReceive('getBaseName')->once()->andReturn('rrze-sso/rrze-sso.php');
        Functions\when('RRZE\SSO\plugin')->justReturn($plugin);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_plugin_data')->justReturn(array('Name' => 'RRZE SSO'));
        Functions\when('is_plugin_active_for_network')->justReturn(false);
        Functions\when('esc_html')->returnArg();
        Functions\when('add_action')->alias(
            function (string $hook, callable $callback) use (&$actions): void {
                $actions[$hook][] = $callback;
            }
        );
        $service = new SimpleSAML();

        self::assertFalse($service->loaded());
        ($actions['admin_init'][0])();
        ob_start();
        ($actions['admin_notices'][0])();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('RRZE SSO', $output);
        self::assertStringContainsString('could not be loaded', $output);
    }

    public function testUndefinedMethodIsLoggedWithoutThrowingInNormalMode(): void
    {
        (new SimpleSAML())->undefinedMethod('argument');

        self::assertTrue(true);
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
