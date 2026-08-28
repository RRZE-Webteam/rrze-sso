<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Main;
use RRZE\SSO\Plugin;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;
use RuntimeException;
use SimpleSAML\Auth\Simple as AuthClient;

#[CoversClass(Main::class)]
class MainTest extends TestCase
{
    /** @var array<string, mixed> */
    private $options = array('force_sso' => 0);

    protected function setUp(): void
    {
        parent::setUp();

        $this->options = array('force_sso' => 0);
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->alias(
            fn(string $name) => 'rrze_sso' === $name ? $this->options : false
        );
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
    }

    public function testPageDetectionUsesCurrentWordPressScript(): void
    {
        $main = new TestableMain();
        unset($GLOBALS['pagenow']);

        self::assertFalse($main->isLoginPageForTest());
        self::assertFalse($main->isUserNewPageForTest());

        $GLOBALS['pagenow'] = 'wp-login.php';
        self::assertTrue($main->isLoginPageForTest());
        self::assertFalse($main->isUserNewPageForTest());

        $GLOBALS['pagenow'] = 'user-new.php';
        self::assertTrue($main->isUserNewPageForTest());
        unset($GLOBALS['pagenow']);
    }

    public function testAdminAssetsAreOnlyEnqueuedForSsoSettings(): void
    {
        $plugin = Mockery::mock(Plugin::class);
        $plugin->shouldReceive('getBasename')->zeroOrMoreTimes()->andReturn('rrze-sso/rrze-sso.php');
        $plugin->shouldReceive('getVersion')->zeroOrMoreTimes()->andReturn('2.0.0');
        Functions\when('RRZE\SSO\plugin')->justReturn($plugin);
        Functions\when('plugins_url')->alias(
            static fn(string $path): string => 'https://example.org/plugins/rrze-sso/' . $path
        );
        Functions\expect('wp_enqueue_style')
            ->once()
            ->with(
                'rrze-sso-settings',
                'https://example.org/plugins/rrze-sso/build/admin.css',
                array(),
                '2.0.0'
            );
        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'rrze-sso-settings',
                'https://example.org/plugins/rrze-sso/build/admin.js',
                array('jquery'),
                '2.0.0'
            );

        $main = new TestableMain();
        $main->adminEnqueueScripts('users.php');
        $main->adminEnqueueScripts('settings_page_sso');
    }

    public function testDisableFunctionStopsRequestWithExplanation(): void
    {
        Functions\when('__')->returnArg();
        Functions\when('wp_die')->alias(
            static function (string $message): void {
                throw new MainRequestTerminated($message);
            }
        );

        $this->expectException(MainRequestTerminated::class);
        $this->expectExceptionMessage('Disabled function.');

        (new TestableMain())->disableFunction();
    }

    public function testLoadedDisablesForcedSsoWhenLibraryCannotLoad(): void
    {
        $this->options['force_sso'] = 1;
        $service = Mockery::mock(SimpleSAML::class);
        $service->shouldReceive('loaded')->once()->andReturn(false);
        $service->shouldReceive('getIdentityProviders')->once()->andReturn(array());
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);
        Functions\expect('update_option')
            ->once()
            ->withArgs(
                static fn(string $name, array $options): bool => 'rrze_sso' === $name
                    && 0 === $options['force_sso']
            );

        (new TestableMain())->loaded();

        self::assertTrue(true);
    }

    public function testLoadedOnlyRegistersSettingsWhenForcedSsoIsDisabled(): void
    {
        $service = Mockery::mock(SimpleSAML::class);
        $service->shouldReceive('getIdentityProviders')->once()->andReturn(array());
        $service->shouldNotReceive('loaded');
        $service->shouldNotReceive('getAuthSimple');
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);
        Functions\expect('add_filter')
            ->never()
            ->with('is_rrze_sso_active', '__return_true');

        (new TestableMain())->loaded();

        self::assertTrue(true);
    }

    public function testLoadedRegistersForcedSsoIntegrations(): void
    {
        $this->options['force_sso'] = 1;
        $client = new AuthClient('test-sp');
        $service = Mockery::mock(SimpleSAML::class);
        $service->shouldReceive('loaded')->once()->andReturn(true);
        $service->shouldReceive('getIdentityProviders')->once()->andReturn(array());
        $service->shouldReceive('getAuthSimple')->once()->andReturn($client);
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);
        Functions\when('apply_filters')->alias(static fn(string $hook, $value) => $value);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('is_admin')->justReturn(false);
        unset($GLOBALS['pagenow']);

        (new TestableMain())->loaded();

        self::assertTrue(true);
    }

    public function testLoadedStopsWhenAuthenticationClientIsUnavailable(): void
    {
        $this->options['force_sso'] = 1;
        $service = Mockery::mock(SimpleSAML::class);
        $service->shouldReceive('loaded')->once()->andReturn(true);
        $service->shouldReceive('getIdentityProviders')->once()->andReturn(array());
        $service->shouldReceive('getAuthSimple')->once()->andReturn(null);
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);

        (new TestableMain())->loaded();

        self::assertTrue(true);
    }

    public function testRegistrationAndUserPageRequestsAreRedirected(): void
    {
        Functions\when('site_url')->justReturn('https://example.org/wp-login.php');
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_redirect')->alias(
            static function (string $url): void {
                throw new MainRequestTerminated($url);
            }
        );
        $main = new TestableMain();

        $GLOBALS['pagenow'] = 'wp-login.php';
        $_REQUEST['action'] = 'register';
        try {
            $main->registerRedirect();
            self::fail('Registration redirect did not terminate the request.');
        } catch (MainRequestTerminated $exception) {
            self::assertSame('https://example.org/wp-login.php', $exception->getMessage());
        }

        $GLOBALS['pagenow'] = 'user-new.php';
        try {
            $main->redirectUserNewForTest();
            self::fail('User page redirect did not terminate the request.');
        } catch (MainRequestTerminated $exception) {
            self::assertSame('users.php?page=usernew', $exception->getMessage());
        }

        unset($GLOBALS['pagenow'], $_REQUEST['action']);
    }

    public function testUnrelatedLoginAndAdminRequestsAreNotRedirected(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\expect('wp_redirect')->never();
        $main = new TestableMain();

        $GLOBALS['pagenow'] = 'wp-login.php';
        $_REQUEST['action'] = 'login';
        $main->registerRedirect();

        $GLOBALS['pagenow'] = 'user-new.php';
        $main->redirectUserNewForTest();

        unset($GLOBALS['pagenow'], $_REQUEST['action']);
        self::assertTrue(true);
    }
}

class TestableMain extends Main
{
    public function isLoginPageForTest(): bool
    {
        return $this->isLoginPage();
    }

    public function isUserNewPageForTest(): bool
    {
        return $this->isUserNewPage();
    }

    public function redirectUserNewForTest(): void
    {
        $this->userNewPageRedirect();
    }
}

class MainRequestTerminated extends RuntimeException
{
}
