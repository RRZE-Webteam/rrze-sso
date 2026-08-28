<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\UsersMenu;
use WP_Error;

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

    public function testUserNewPageUsesInstallationAppropriateCapabilities(): void
    {
        $previousSubmenu = $GLOBALS['submenu'] ?? null;
        $capabilities = array();
        Functions\when('__')->returnArg();
        Functions\when('remove_submenu_page')->justReturn(null);
        Functions\when('add_submenu_page')->alias(
            function (...$args) use (&$capabilities): string {
                $capabilities[] = $args[3];
                return 'users_page_usernew';
            }
        );

        foreach (array(false, true) as $multisite) {
            $GLOBALS['submenu'] = array('users.php' => array(5 => 'Add New'));
            Functions\when('is_multisite')->justReturn($multisite);
            UsersMenu::userNewPage();
        }

        self::assertSame(array('create_users', 'promote_users'), $capabilities);
        if (null === $previousSubmenu) {
            unset($GLOBALS['submenu']);
        } else {
            $GLOBALS['submenu'] = $previousSubmenu;
        }
    }

    public function testUserNewRendersMultisiteFormsAndStatusMessage(): void
    {
        $previousGet = $_GET;
        $previousPost = $_POST;
        $previousServer = $_SERVER;
        $_GET = array('update' => 'addnoconfirmation', 'user_id' => '17');
        $_POST = array(
            'createuser' => '1',
            'user_idp' => 'idp-one',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
            'role' => 'editor',
            'noconfirmation' => '1',
        );
        $_SERVER['REQUEST_URI'] = '/wp-admin/users.php?page=usernew';

        $service = Mockery::mock(SimpleSAML::class);
        $service->shouldReceive('getIdentityProviders')
            ->once()
            ->andReturn(array('idp-one' => 'Identity Provider One'));
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('is_super_admin')->justReturn(true);
        Functions\when('wp_is_large_network')->justReturn(false);
        Functions\when('wp_enqueue_script')->justReturn(null);
        Functions\when('apply_filters')->alias(static fn(string $hook, $value) => $value);
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html_e')->alias(static function (string $value): void { echo $value; });
        Functions\when('esc_url')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('sanitize_title')->alias(static fn(string $value): string => strtolower($value));
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\when('wp_unslash')->returnArg();
        Functions\when('absint')->alias(static fn($value): int => abs((int) $value));
        Functions\when('get_edit_user_link')->alias(static fn(int $id): string => 'users.php?user_id=' . $id);
        Functions\when('add_query_arg')->alias(
            static fn(string $key, string $value, string $url): string => $url . '&' . $key . '=' . $value
        );
        Functions\when('get_option')->justReturn('subscriber');
        Functions\when('admin_url')->alias(static fn(string $path): string => 'https://example.org/wp-admin/' . $path);
        Functions\when('selected')->justReturn('');
        Functions\when('checked')->justReturn('');
        Functions\when('wp_dropdown_roles')->justReturn(null);
        Functions\when('wp_nonce_field')->justReturn(null);
        Functions\when('submit_button')->justReturn(null);

        ob_start();
        UsersMenu::userNew();
        $output = (string) ob_get_clean();
        $_GET = $previousGet;
        $_POST = $previousPost;
        $_SERVER = $previousServer;

        self::assertStringContainsString('User has been added to your site.', $output);
        self::assertStringContainsString('Identity Provider One', $output);
        self::assertStringContainsString('_admin_add-user', $output);
        self::assertStringContainsString('_admin_create-user', $output);
    }

    public function testUserNewRendersEveryCoreStatusAndSerializedError(): void
    {
        $previousGet = $_GET;
        $previousPost = $_POST;
        $multisite = true;
        $_POST = array();
        Functions\when('is_multisite')->alias(static function () use (&$multisite): bool {
            return $multisite;
        });
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('is_super_admin')->justReturn(false);
        Functions\when('wp_enqueue_script')->justReturn(null);
        Functions\when('__')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\when('get_option')->justReturn('subscriber');
        Functions\when('admin_url')->returnArg();

        $statuses = array(
            'newuserconfirmation',
            'add',
            'addexisting',
            'could_not_add',
            'created_could_not_add',
            'does_not_exist',
            'enter_email',
        );
        $combinedOutput = '';
        foreach ($statuses as $status) {
            $_GET = array('update' => $status);
            ob_start();
            UsersMenu::userNew();
            $combinedOutput .= (string) ob_get_clean();
        }

        $multisite = false;
        $_GET = array('update' => 'add');
        ob_start();
        UsersMenu::userNew();
        $combinedOutput .= (string) ob_get_clean();

        $_GET = array(
            'error' => base64_encode(serialize(new WP_Error('custom', 'Serialized error'))),
        );
        ob_start();
        UsersMenu::userNew();
        $combinedOutput .= (string) ob_get_clean();

        $_GET = $previousGet;
        $_POST = $previousPost;

        self::assertStringContainsString('Invitation email sent to new user.', $combinedOutput);
        self::assertStringContainsString('already a member', $combinedOutput);
        self::assertStringContainsString('could not be added', $combinedOutput);
        self::assertStringContainsString('does not exist', $combinedOutput);
        self::assertStringContainsString('Serialized error', $combinedOutput);
        self::assertStringContainsString('User added.', $combinedOutput);
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
