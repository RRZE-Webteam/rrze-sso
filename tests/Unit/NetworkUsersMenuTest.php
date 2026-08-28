<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\NetworkUsersMenu;
use RRZE\SSO\Tests\TestCase;

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
