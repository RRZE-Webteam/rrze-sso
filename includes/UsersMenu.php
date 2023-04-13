<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class UsersMenu
{
    public static function userNewPage()
    {
        global $submenu;

        remove_submenu_page('users.php', 'user-new.php');

        if (is_multisite()) {
            $capability = 'promote_users';
        } else {
            $capability = 'create_users';
        }

        $submenuPage = add_submenu_page(
            'users.php',
            __('Add New', 'rrze-sso'),
            __('Add New', 'rrze-sso'),
            $capability,
            'usernew',
            [__CLASS__, 'userNew']
        );

        add_action(sprintf('load-%s', $submenuPage), [__CLASS__, 'userNewHelp']);

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
        $help = '<p>' . __('To add a new user to your site, fill in the form on this screen and click the Add New User button at the bottom.') . '</p>';

        if (is_multisite()) {
            $help .= '<p>' . __('Because this is a multisite installation, you may add accounts that already exist on the Network by specifying a username or email, and defining a role. For more options, such as specifying a password, you have to be a Network Administrator and use the hover link under an existing user&#8217;s name to Edit the user profile under Network Admin > All Users.') . '</p>';
            $help .= '<p>' . __('New users will receive an email letting them know they&#8217;ve been added as a user for your site. This email will also contain their password. Check the box if you do not want the user to receive a welcome email.') . '</p>';
        } else {
            $help .= '<p>' . __('New users are automatically assigned a password, which they can change after logging in. You can view or edit the assigned password by clicking the Show Password button. The username cannot be changed once the user has been added.') . '</p>';
            $help .= '<p>' . __('By default, new users will receive an email letting them know they&#8217;ve been added as a user for your site. This email will also contain a password reset link. Uncheck the box if you do not want to send the new user a welcome email.') . '</p>';
        }

        $help .= '<p>' . __('Remember to click the Add New User button at the bottom of this screen when you are finished.') . '</p>';

        get_current_screen()->add_help_tab(
            array(
                'id' => 'overview',
                'title' => __('Overview'),
                'content' => $help,
            )
        );

        get_current_screen()->add_help_tab(
            array(
                'id' => 'user-roles',
                'title' => __('User Roles'),
                'content' => '<p>' . __('Here is a basic overview of the different user roles and the permissions associated with each one:') . '</p>' .
                    '<ul>' .
                    '<li>' . __('Subscribers can read comments/comment/receive newsletters, etc. but cannot create regular site content.') . '</li>' .
                    '<li>' . __('Contributors can write and manage their posts but not publish posts or upload media files.') . '</li>' .
                    '<li>' . __('Authors can publish and manage their own posts, and are able to upload files.') . '</li>' .
                    '<li>' . __('Editors can publish posts, manage posts as well as manage other people&#8217;s posts, etc.') . '</li>' .
                    '<li>' . __('Administrators have access to all the administration features.') . '</li>' .
                    '</ul>',
            )
        );

        get_current_screen()->set_help_sidebar(
            '<p><strong>' . __('For more information:') . '</strong></p>' .
                '<p>' . __('<a href="https://wordpress.org/support/article/users-add-new-screen/">Documentation on Adding New Users</a>') . '</p>' .
                '<p>' . __('<a href="https://wordpress.org/support/">Support</a>') . '</p>'
        );
    }

    public static function userNew()
    {
        // Used in the HTML title tag.
        $title = __('Add New User');
        $parent_file = 'users.php';

        $do_both = false;
        if (is_multisite() && current_user_can('promote_users') && current_user_can('create_users')) {
            $do_both = true;
        }

        wp_enqueue_script('wp-ajax-response');
        wp_enqueue_script('user-profile');

        /**
         * Filters whether to enable user auto-complete for non-super admins in Multisite.
         *
         * @since 3.4.0
         *
         * @param bool $enable Whether to enable auto-complete for non-super admins. Default false.
         */
        if (
            is_multisite() && current_user_can('promote_users') && !wp_is_large_network('users')
            && (current_user_can('manage_network_users') || apply_filters('autocomplete_users_for_site_admins', false))
        ) {
            wp_enqueue_script('user-suggest');
        }

        if (isset($_GET['update'])) {
            $messages = array();
            if (is_multisite()) {
                $edit_link = '';
                if ((isset($_GET['user_id']))) {
                    $user_id_new = absint($_GET['user_id']);
                    if ($user_id_new) {
                        $edit_link = esc_url(add_query_arg('wp_http_referer', urlencode(wp_unslash($_SERVER['REQUEST_URI'])), get_edit_user_link($user_id_new)));
                    }
                }

                switch ($_GET['update']) {
                    case 'newuserconfirmation':
                        $messages[] = __('Invitation email sent to new user. A confirmation link must be clicked before their account is created.');
                        break;
                    case 'add':
                        $messages[] = __('Invitation email sent to user. A confirmation link must be clicked for them to be added to your site.');
                        break;
                    case 'addnoconfirmation':
                        $message = __('User has been added to your site.');

                        if ($edit_link) {
                            $message .= sprintf(' <a href="%s">%s</a>', $edit_link, __('Edit user'));
                        }

                        $messages[] = $message;
                        break;
                    case 'addexisting':
                        $messages[] = __('That user is already a member of this site.');
                        break;
                    case 'could_not_add':
                        $add_user_errors = new \WP_Error('could_not_add', __('That user could not be added to this site.'));
                        break;
                    case 'created_could_not_add':
                        $add_user_errors = new \WP_Error('created_could_not_add', __('User has been created, but could not be added to this site.'));
                        break;
                    case 'does_not_exist':
                        $add_user_errors = new \WP_Error('does_not_exist', __('The requested user does not exist.'));
                        break;
                    case 'enter_email':
                        $add_user_errors = new \WP_Error('enter_email', __('Please enter a valid email address.'));
                        break;
                }
            } else {
                if ('add' === $_GET['update']) {
                    $messages[] = __('User added.');
                }
            }
        } ?>
        <div class="wrap">
            <h2 id="add-new-user">
                <?php
                if (current_user_can('create_users')) {
                    _e("Add New User", 'rrze-sso');
                } elseif (current_user_can('promote_users')) {
                    _e("Add Existing User", 'rrze-sso');
                }
                ?>
            </h2>

            <?php if (isset($errors) && is_wp_error($errors)) : ?>
                <div class="error">
                    <ul>
                        <?php
                        foreach ($errors->get_error_messages() as $err) {
                            echo "<li>$err</li>\n";
                        } ?>
                    </ul>
                </div>
            <?php endif;

            if (!empty($messages)) {
                foreach ($messages as $msg) {
                    echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
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
            <div id="ajax-response"></div>

            <?php
            if (is_multisite() && current_user_can('promote_users')) {
                if ($do_both) {
                    echo '<h2 id="add-existing-user">' . __('Add Existing User') . '</h2>';
                }
                if (!current_user_can('manage_network_users')) {
                    echo '<p>' . __('Enter the email address of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.') . '</p>';
                    $label = __('Email');
                    $type  = 'email';
                } else {
                    echo '<p>' . __('Enter the email address or username of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.') . '</p>';
                    $label = __('Email or Username');
                    $type  = 'text';
                } ?>
                <form action="<?php echo esc_url(admin_url('users.php?page=usernew')); ?>" method="post" name="adduser" id="adduser" class="validate" novalidate="novalidate">
                    <input type="hidden" name="action" value="_admin_add-user" />
                    <?php wp_nonce_field('add-user', '_wpnonce_add-user') ?>

                    <table class="form-table">
                        <tr class="form-field form-required">
                            <th scope="row"><label for="adduser-email"><?php echo $label; ?></label></th>
                            <td><input name="email" type="<?php echo $type; ?>" id="adduser-email" class="wp-suggest-user" value="" /></td>
                        </tr>
                        <tr class="form-field">
                            <th scope="row"><label for="adduser-role"><?php _e('Role'); ?></label></th>
                            <td><select name="role" id="adduser-role">
                                    <?php wp_dropdown_roles(get_option('default_role')); ?>
                                </select>
                            </td>
                        </tr>
                        <?php if (is_super_admin()) {
                        ?>
                            <tr>
                                <th scope="row"><label for="adduser-noconfirmation"><?php _e('Skip Confirmation Email') ?></label></th>
                                <td><label for="adduser-noconfirmation"><input type="checkbox" name="noconfirmation" id="adduser-noconfirmation" value="1" /> <?php _e('Add the user without sending an email that requires their confirmation.'); ?></label></td>
                            </tr>
                        <?php
                        } ?>
                    </table>
                    <?php submit_button(__('Add Existing User'), 'primary', 'adduser', true, array('id' => 'addusersub')); ?>
                </form>
            <?php
            } // is_multisite()

            if (current_user_can('create_users')) {
                if ($do_both) {
                    echo '<h3 id="create-new-user">' . __('Add New User') . '</h3>';
                } ?>
                <p><?php _e('Create a brand new user and add them to this site.'); ?></p>
                <form action="<?php echo esc_url(admin_url('users.php?page=usernew')); ?>" method="post" name="createuser" id="createuser" class="validate" novalidate="novalidate">
                    <input type="hidden" name="action" value="_admin_create-user" />
                    <?php wp_nonce_field('create-user', '_wpnonce_create-user'); ?>
                    <?php
                    $creating = isset($_POST['createuser']);
                    $new_user_idp = $creating && isset($_POST['user_idp']) ? wp_unslash($_POST['user_idp']) : '';
                    $new_user_login = $creating && isset($_POST['user_login']) ? wp_unslash($_POST['user_login']) : '';
                    $new_user_email = $creating && isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
                    $new_user_role = $creating && isset($_POST['role']) ? wp_unslash($_POST['role']) : '';
                    $new_user_send_notification = $creating && !isset($_POST['send_user_notification']) ? false : true;
                    $new_user_ignore_pass = $creating && isset($_POST['noconfirmation']) ? wp_unslash($_POST['noconfirmation']) : '';
                    ?>
                    <table class="form-table">
                        <tr class="form-field form-required">
                            <th scope="row"><label for="user_idp"><?php _e('Identity Provider', 'rrze-sso'); ?> <span class="description"><?php _e("(required)"); ?></span></label></th>
                            <td><?php
                                echo '<select id="user_idp" name="user_idp">';
                                echo '<option  value="">&mdash; ' . __('Select an Identity Provider', 'rrze-sso') . ' &mdash;</option>';
                                foreach (simpleSAML()->getIdentityProviders() as $key => $value) {
                                    $key = sanitize_title($key);
                                    echo '<option  value="' . $key . '" ' . selected($new_user_idp, $key, false) . '>' . $value . '</option>';
                                }
                                echo '</select>';
                                ?></td>
                        </tr>
                        <tr class="form-field form-required">
                            <th scope="row"><label for="user_login"><?php _e('User Identifier', 'rrze-sso'); ?> <span class="description"><?php _e('(required)'); ?></span></label></th>
                            <td><input name="user_login" type="text" id="user_login" value="<?php echo esc_attr($new_user_login); ?>" aria-required="true" /></td>
                        </tr>
                        <tr class="form-field form-required">
                            <th scope="row"><label for="email"><?php _e('Email'); ?> <span class="description"><?php _e('(required)'); ?></span></label></th>
                            <td><input name="email" type="email" id="email" value="<?php echo esc_attr($new_user_email); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Send User Notification'); ?></th>
                            <td>
                                <input type="checkbox" name="send_user_notification" id="send_user_notification" value="1" <?php checked($new_user_send_notification); ?> />
                                <label for="send_user_notification"><?php _e('Send the new user an email about their account.'); ?></label>
                            </td>
                        </tr>
                        <?php if (current_user_can('promote_users')) { ?>
                            <tr class="form-field">
                                <th scope="row"><label for="role"><?php _e("Role"); ?></label></th>
                                <td><select name="role" id="role">
                                        <?php
                                        if (!$new_user_role) {
                                            $new_user_role = !empty($current_role) ? $current_role : get_option('default_role');
                                        }
                                        wp_dropdown_roles($new_user_role); ?>
                                    </select>
                                </td>
                            </tr>
                        <?php } // current_user_can('promote_users') 
                        ?>
                        <?php if (is_multisite() && is_super_admin()) {
                        ?>
                            <tr>
                                <th scope="row"><label for="noconfirmation"><?php _e('Skip Confirmation Email') ?></label></th>
                                <td>
                                    <label for="noconfirmation">
                                        <input type="checkbox" name="noconfirmation" id="noconfirmation" value="1" <?php checked($new_user_ignore_pass); ?> />
                                        <?php _e('Add the user without sending an email that requires their confirmation.'); ?>
                                    </label>
                                </td>
                            </tr>
                        <?php
                        } ?>
                    </table>

                    <?php submit_button(__('Add New User'), 'primary', 'createuser', true, array('id' => 'createusersub')); ?>

                </form>
            <?php
            } // current_user_can('create_users')
            ?>
        </div>
<?php
    }
}
