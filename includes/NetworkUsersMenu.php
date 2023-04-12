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
        if (isset($_GET['update'])) {
            $messages = array();
            if ('added' == $_GET['update']) {
                $messages[] = __('User added.');
            }
        }

        // Used in the HTML title tag.
        $title = __('Add New User');
        $parent_file = 'users.php'; ?>
        <div class="wrap">
            <h2 id="add-new-user"><?php _e('Add New User') ?></h2>
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
            <form action="<?php echo esc_url(network_admin_url('users.php?page=usernew')); ?>" id="adduser" method="post" novalidate="novalidate">
                <input type="hidden" name="action" value="_network_add-user" />
                <?php wp_nonce_field('add-user', '_wpnonce_add-user'); ?>
                <table class="form-table" role="presentation">
                    <tr class="form-field form-required">
                        <th scope="row"><?php _e("Identity Provider", 'rrze-sso') ?></th>
                        <td><?php
                            echo '<select name="user[idp]">';
                            echo '<option  value="">&mdash; ' . __('Select an Identity Provider', 'rrze-sso') . ' &mdash;</option>';
                            foreach (simpleSAML()->getIdentityProviders() as $key => $value) {
                                echo '<option  value="' . sanitize_title($key) . '">' . $value . '</option>';
                            }
                            echo '</select>';
                            ?></td>
                    </tr>
                    <tr class="form-field form-required">
                        <th scope="row"><label for="username"><?php _e('User Identifier', 'rrze-sso'); ?></label></th>
                        <td><input type="text" class="regular-text" name="user[username]" id="username" autocapitalize="none" autocorrect="off" maxlength="60" /></td>
                    </tr>
                    <tr class="form-field form-required">
                        <th scope="row"><label for="email"><?php _e('Email'); ?></label></th>
                        <td><input type="email" class="regular-text" name="user[email]" id="email" /></td>
                    </tr>
                </table>
                <?php submit_button(__('Add User'), 'primary', 'add-user'); ?>
            </form>
        </div>
<?php
    }
}
