<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Main;
use RRZE\SSO\Plugin;
use RRZE\SSO\Tests\TestCase;
use RuntimeException;

#[CoversClass(Main::class)]
class MainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->justReturn(array('force_sso' => 0));
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
}

class MainRequestTerminated extends RuntimeException
{
}
