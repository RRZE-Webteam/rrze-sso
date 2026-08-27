<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Customizes the site administration user menu for SSO user management.
 *
 * Replaces the WordPress add-user screen with an SSO-aware page and provides
 * contextual help for site administrators.
 */
class UsersMenu
{
    /**
     * Registers the custom add-user submenu page in the site administration.
     *
     * Replaces the WordPress core submenu entry while preserving its expected
     * position in the Users menu.
     *
     * @return void
     */
    public static function userNewPage()
    {
        global $submenu;

        remove_submenu_page('users.php', 'user-new.php');

        if (is_multisite()) {
            $capability = 'promote_users';
        } else {
            $capability = 'create_users';
        }

        $submenu_page = add_submenu_page(
            'users.php',
            __('Add New', 'rrze-sso'),
            __('Add New', 'rrze-sso'),
            $capability,
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

    /**
     * Adds contextual help to the custom site administration add-user screen.
     *
     * @return void
     */
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

    /**
     * Prepares and renders the SSO-aware site administration add-user page.
     *
     * @return void
     */
    public static function userNew()
    {
        $is_multisite = is_multisite();
        $can_create_users = current_user_can('create_users');
        $can_promote_users = current_user_can('promote_users');
        $can_manage_network_users = current_user_can('manage_network_users');
        $is_super_admin = is_super_admin();
        $do_both = $is_multisite && $can_promote_users && $can_create_users;

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
            $is_multisite && $can_promote_users && !wp_is_large_network('users')
            && ($can_manage_network_users || apply_filters('autocomplete_users_for_site_admins', false))
        ) {
            wp_enqueue_script('user-suggest');
        }

        $messages = array();
        $add_user_errors = '';

        if (isset($_GET['update'])) {
            if ($is_multisite) {
                $edit_link = '';
                if (isset($_GET['user_id'])) {
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
        }

        if (isset($_GET['error'])) {
            $add_user_errors = @unserialize(base64_decode($_GET['error']));
        }

        $creating = isset($_POST['createuser']);
        $new_user_idp = $creating && isset($_POST['user_idp']) ? wp_unslash($_POST['user_idp']) : '';
        $new_user_login = $creating && isset($_POST['user_login']) ? wp_unslash($_POST['user_login']) : '';
        $new_user_email = $creating && isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $new_user_role = $creating && isset($_POST['role']) ? wp_unslash($_POST['role']) : '';
        $new_user_send_notification = !$creating || isset($_POST['send_user_notification']);
        $new_user_ignore_pass = $creating && isset($_POST['noconfirmation']) ? wp_unslash($_POST['noconfirmation']) : '';

        if (!$new_user_role) {
            $new_user_role = get_option('default_role');
        }

        $identity_providers = $can_create_users ? simpleSAML()->getIdentityProviders() : array();
        $form_action = admin_url('users.php?page=usernew');
        $existing_user_label = $can_manage_network_users ? __('Email or Username') : __('Email');
        $existing_user_input_type = $can_manage_network_users ? 'text' : 'email';

        require dirname(__DIR__) . '/templates/users/user-new.php';
    }
}
