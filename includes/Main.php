<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Main
{
    /**
     * Option name.
     * @var string
     */
    protected $optionName;

    /**
     * Settings options.
     * @var object
     */
    protected $options;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();
    }

    public function loaded()
    {
        if ($this->options->force_sso) {
            if (!simpleSAML()->loaded()) {
                $this->options->force_sso = 0;
                update_option($this->optionName, (array) $this->options);
            }
        }

        $settings = new Settings();
        $settings->loaded();

        if (!$this->options->force_sso) {
            return;
        }

        $authSimple = simpleSAML()->getAuthSimple();
        if (!is_a($authSimple, '\SimpleSAML\Auth\Simple')) {
            return;
        }

        $authenticate = new Authenticate($authSimple);
        $authenticate->loaded();

        // add_action('init', function () use ($authSimple) {
        //     if (
        //         is_user_logged_in()
        //         && !$authSimple->isAuthenticated()
        //     ) {
        //         wp_destroy_current_session();
        //         wp_clear_auth_cookie();
        //         wp_set_current_user(0);
        //     }
        // });

        $this->registerRedirect();
        $this->userNewPageRedirect();

        if (current_user_can('manage_options')) {
            $userList = new UsersList();
            $userList->loaded();
        }

        // Fires before the lost password form (die).
        add_action('lost_password', [$this, 'disableFunction']);
        // Fires before a new password is retrieved (die).
        add_action('retrieve_password', [$this, 'disableFunction']);
        // Fires before the user’s password is reset (die).
        add_action('password_reset', [$this, 'disableFunction']);
        // Fires before the password reset procedure is validated (die).
        add_action('validate_password_reset', [$this, 'disableFunction']);

        // Filters the display of the password fields (disable).
        add_filter('show_password_fields', '__return_false');

        // Send a confirmation request email to a user 
        // when they sign up for a new user account (disable).
        add_filter('wpmu_signup_user_notification', '__return_false');
        // Notify a user that their account activation 
        // has been successful (disable).
        add_filter('wpmu_welcome_user_notification', '__return_false');

        // Filters whether to show the Add Existing User form 
        // on the Multisite Users screen (disable).
        add_filter('show_network_site_users_add_existing_form', '__return_false');
        // Filters whether to show the Add New User form 
        // on the Multisite Users screen (disable).
        add_filter('show_network_site_users_add_new_form', '__return_false');

        // Custom user registration menu.
        add_action('network_admin_menu', [__NAMESPACE__ . '\NetworkUsersMenu', 'userNewPage']);
        add_action('admin_menu', [__NAMESPACE__ . '\UsersMenu', 'userNewPage']);
        // Custom user registration functions.
        add_action('admin_init', [__NAMESPACE__ . '\Users', 'userNewAction']);

        add_filter('is_rrze_sso_active', '__return_true');
        // Backward compatibility
        add_filter('is_fau_websso_active', '__return_true');
    }

    public function registerRedirect()
    {
        if ($this->isLoginPage() && isset($_REQUEST['action']) && $_REQUEST['action'] == 'register') {
            wp_redirect(site_url('wp-login.php', 'login'));
            exit;
        }
    }

    protected function userNewPageRedirect()
    {
        if (is_admin() && $this->isUserNewPage()) {
            wp_redirect('users.php?page=usernew');
            exit;
        }
    }

    protected function isUserNewPage()
    {
        if (isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], ['user-new.php']);
        }
        return false;
    }

    protected function isLoginPage()
    {
        if (isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], ['wp-login.php']);
        }
        return false;
    }

    public function disableFunction()
    {
        $output = __("Disabled function.", 'rrze-sso');
        wp_die($output);
    }

    /**
     * Register admin styles & scripts.
     */
    public function adminEnqueueScripts($hook)
    {
        if (!str_contains($hook, 'settings_page_sso')) {
            return;
        }

        wp_enqueue_style(
            'rrze-sso-settings',
            plugins_url('build/admin.css', plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );

        wp_enqueue_script(
            'rrze-sso-setings',
            plugins_url('build/admin.js', plugin()->getBasename()),
            ['jquery'],
            plugin()->getVersion()
        );
    }
}
