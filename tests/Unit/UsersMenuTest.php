<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\UsersMenu;

#[CoversClass(UsersMenu::class)]
class UsersMenuTest extends TestCase
{
    public function testUserNewHelpDescribesSingleSiteUserCreation(): void
    {
        $screen = new UsersMenuScreen();
        Functions\when('__')->returnArg();
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_current_screen')->justReturn($screen);

        UsersMenu::userNewHelp();

        self::assertCount(2, $screen->tabs);
        self::assertSame('overview', $screen->tabs[0]['id']);
        self::assertSame('user-roles', $screen->tabs[1]['id']);
        self::assertStringContainsString('automatically assigned a password', $screen->tabs[0]['content']);
        self::assertStringContainsString('Documentation on Adding New Users', $screen->sidebar);
    }

    public function testUserNewHelpDescribesMultisiteInvitations(): void
    {
        $screen = new UsersMenuScreen();
        Functions\when('__')->returnArg();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_screen')->justReturn($screen);

        UsersMenu::userNewHelp();

        self::assertStringContainsString('already exist on the Network', $screen->tabs[0]['content']);
        self::assertStringContainsString('welcome email', $screen->tabs[0]['content']);
    }
}

class UsersMenuScreen
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
