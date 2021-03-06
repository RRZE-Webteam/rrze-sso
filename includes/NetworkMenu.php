<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class NetworkMenu
{
    public static function userNewPage()
    {
        global $submenu;

        remove_submenu_page('users.php', 'user-new.php');

        $submenu_page = add_submenu_page('users.php', __('Add New', 'rrze-sso'), __('Add New', 'rrze-sso'), 'manage_network_users', 'usernew', [__CLASS__, 'userNew']);

        add_action(sprintf('load-%s', $submenu_page), [__CLASS__, 'userNewHelp']);

        if (isset($submenu['users.php'])) {
            foreach ($submenu['users.php'] as $key => $value) {
                if ($value == __("Add New", 'rrze-sso')) {
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
        $help = '<p>' . __("Add User will set up a new user account on the network.", 'rrze-sso') . '</p>';
        $help .= '<p>' . __("Users who are signed up to the network without a site are added as subscribers to the main website, giving them profile pages to manage their accounts. These users will only see Dashboard and My Sites in the main navigation until a site is created for them.", 'rrze-sso') . '</p>';

        get_current_screen()->add_help_tab(array(
            'id' => 'overview',
            'title' => __("Overall view", 'rrze-sso'),
            'content' => $help,
        ));

        get_current_screen()->add_help_tab(array(
            'id' => 'user-roles',
            'title' => __("User Roles", 'rrze-sso'),
            'content' => '<p>' . __("Here is a basic overview of the different user roles and the permissions associated with each one:", 'rrze-sso') . '</p>' .
                '<ul>' .
                '<li>' . __("Subscribers can read comments/comment/receive newsletters, etc. but cannot create regular site content.", 'rrze-sso') . '</li>' .
                '<li>' . __("Contributors can write and manage their posts but not publish posts or upload media files.", 'rrze-sso') . '</li>' .
                '<li>' . __("Authors can publish and manage their own posts, and are able to upload files.", 'rrze-sso') . '</li>' .
                '<li>' . __("Editors can publish posts, manage posts as well as manage other people's posts, etc.", 'rrze-sso') . '</li>' .
                '<li>' . __("Administrators have access to all the administration features.", 'rrze-sso') . '</li>' .
                '</ul>'
        ));
    }

    public static function userNew()
    {
        if (isset($_GET['update'])) {
            $messages = array();
            if ('added' == $_GET['update']) {
                $messages[] = __("User added.", 'rrze-sso');
            }
        } ?>
        <div class="wrap">
            <h2 id="add-new-user"><?php _e("Add New User", 'rrze-sso') ?></h2>
            <?php
            if (!empty($messages)) {
                foreach ($messages as $msg) {
                    printf('<div id="message" class="updated"><p>%s</p></div>', $msg);
                }
            }

            $add_user_errors = '';
            if (isset($_GET['error'])) {
                $add_user_errors = @unserialize(base64_decode($_GET['error']));
            }

            if (is_wp_error($add_user_errors)) : ?>
                <div class="error">
                    <?php
                    foreach ($add_user_errors->get_error_messages() as $message) {
                        echo "<p>$message</p>";
                    } ?>
                </div>
            <?php endif; ?>
            <form action="<?php echo network_admin_url('users.php?page=usernew&action=add-user'); ?>" id="adduser" method="post">
                <table class="form-table">
                    <tr class="form-field form-required">
                        <th scope="row"><?php _e("IdM Username", 'rrze-sso') ?></th>
                        <td><input type="text" class="regular-text" name="user[username]" /></td>
                    </tr>
                    <tr class="form-field form-required">
                        <th scope="row"><?php _e("Email Address", 'rrze-sso') ?></th>
                        <td><input type="text" class="regular-text" name="user[email]" /></td>
                    </tr>
                </table>
                <?php wp_nonce_field('add-user', '_wpnonce_add-user'); ?>
                <?php submit_button(__("Add New User", 'rrze-sso'), 'primary', 'add-user'); ?>
            </form>
        </div>
<?php
    }
}
