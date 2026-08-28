<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\NetworkUsersMenu;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;
use WP_Error;

#[CoversClass(NetworkUsersMenu::class)]
class NetworkUsersMenuTest extends TestCase
{
    public function testUserNewHelpAddsNetworkGuidance(): void
    {
        $screen = new NetworkUsersMenuScreen();
        Functions\when('__')->returnArg();
        Functions\when('get_current_screen')->justReturn($screen);

        NetworkUsersMenu::userNewHelp();

        self::assertCount(1, $screen->tabs);
        self::assertSame('overview', $screen->tabs[0]['id']);
        self::assertStringContainsString('new user account on the network', $screen->tabs[0]['content']);
        self::assertStringContainsString('Network Users', $screen->sidebar);
    }

    public function testUserNewPageRegistersAndRepositionsSubmenu(): void
    {
        $previousSubmenu = $GLOBALS['submenu'] ?? null;
        $GLOBALS['submenu'] = array(
            'users.php' => array(
                5 => 'All Users',
                6 => 'Add New',
            ),
        );
        Functions\when('__')->returnArg();
        Functions\expect('remove_submenu_page')->once()->with('users.php', 'user-new.php');
        Functions\expect('add_submenu_page')
            ->once()
            ->withArgs(static fn(...$args): bool => 'manage_network_users' === $args[3])
            ->andReturn('users_page_usernew');

        NetworkUsersMenu::userNewPage();

        self::assertSame('Add New', $GLOBALS['submenu']['users.php'][10]);
        self::assertArrayNotHasKey(6, $GLOBALS['submenu']['users.php']);
        if (null === $previousSubmenu) {
            unset($GLOBALS['submenu']);
        } else {
            $GLOBALS['submenu'] = $previousSubmenu;
        }
    }

    public function testUserNewRendersMessagesErrorsAndIdentityProviders(): void
    {
        $previousGet = $_GET;
        $_GET = array(
            'update' => 'added',
            'error' => base64_encode(serialize(new WP_Error('invalid', 'Invalid signup'))),
        );
        $service = Mockery::mock(SimpleSAML::class);
        $service->shouldReceive('getIdentityProviders')
            ->once()
            ->andReturn(array('idp-one' => 'Identity Provider One'));
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html_e')->alias(static function (string $value): void { echo $value; });
        Functions\when('esc_url')->returnArg();
        Functions\when('sanitize_title')->alias(static fn(string $value): string => strtolower($value));
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\when('network_admin_url')->alias(static fn(string $path): string => 'https://example.org/wp-admin/network/' . $path);
        Functions\when('wp_nonce_field')->justReturn(null);
        Functions\when('submit_button')->justReturn(null);

        ob_start();
        NetworkUsersMenu::userNew();
        $output = (string) ob_get_clean();
        $_GET = $previousGet;

        self::assertStringContainsString('User added.', $output);
        self::assertStringContainsString('Invalid signup', $output);
        self::assertStringContainsString('Identity Provider One', $output);
        self::assertStringContainsString('_network_add-user', $output);
    }
}

class NetworkUsersMenuScreen
{
    /** @var array<int, array<string, string>> */
    public $tabs = array();

    /** @var string */
    public $sidebar = '';

    public function add_help_tab(array $tab): void
    {
        $this->tabs[] = $tab;
    }

    public function set_help_sidebar(string $sidebar): void
    {
        $this->sidebar = $sidebar;
    }
}
