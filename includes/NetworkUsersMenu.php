<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class NetworkUsersMenu
{
    public static function userNewPage()
    {
        global $submenu;

        remove_submenu_page('users.php', 'user-new.php');

        $submenu_page = add_submenu_page(
            'users.php',
            __('Add New', 'rrze-sso'),
            __('Add New', 'rrze-sso'),
            'manage_network_users',
            'usernew',
            [__CLASS__, 'userNew']
        );

        add_action(sprintf('load-%s', $submenu_page), [__CLASS__, 'userNewHelp']);

        if (isset($submenu['users.php'])) {
            foreach ($submenu['users.php'] as $key => $value) {
                if ($value == __('Add New', 'rrze-sso')) {
                    break;
                }
            }
            $submenu['users.php'][10] = $submenu['users.php'][$key];
            unset($submenu['users.php'][$key]);
            ksort($submenu['users.php']);
        }
    }

    public static function userNewHelp()
    {
        get_current_screen()->add_help_tab(
            array(
                'id'      => 'overview',
                'title'   => __('Overview'),
                'content' =>
                '<p>' . __('Add User will set up a new user account on the network and send that person an email with username and password.') . '</p>' .
                    '<p>' . __('Users who are signed up to the network without a site are added as subscribers to the main or primary dashboard site, giving them profile pages to manage their accounts. These users will only see Dashboard and My Sites in the main navigation until a site is created for them.') . '</p>',
            )
        );

        get_current_screen()->set_help_sidebar(
            '<p><strong>' . __('For more information:') . '</strong></p>' .
                '<p>' . __('<a href="https://codex.wordpress.org/Network_Admin_Users_Screen">Documentation on Network Users</a>') . '</p>' .
                '<p>' . __('<a href="https://wordpress.org/support/forum/multisite/">Support Forums</a>') . '</p>'
        );
    }

    public static function userNew()
    {
        $messages = array();

        if (isset($_GET['update'])) {
            if ('added' == $_GET['update']) {
                $messages[] = __('User added.');
            }
        }

        $add_user_errors = '';
        if (isset($_GET['error'])) {
            $add_user_errors = @unserialize(base64_decode($_GET['error']));
        }

        $identity_providers = simpleSAML()->getIdentityProviders();
        $form_action = network_admin_url('users.php?page=usernew');

        require dirname(__DIR__) . '/templates/network-users/user-new.php';
    }
}
