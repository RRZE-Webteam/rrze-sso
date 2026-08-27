<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Coordinates plugin initialization and registers the main WordPress hooks.
 *
 * Loads settings for every request and enables the authentication, user
 * management, and password-related integrations when forced SSO is active.
 */
class Main
{
    /**
     * Name of the plugin option stored by WordPress.
     *
     * @var string
     */
    protected $optionName;

    /**
     * Current plugin settings.
     *
     * @var object
     */
    protected $options;

    /**
     * Initializes the option name and current plugin settings.
     *
     * @return void
     */
    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();
    }

    /**
     * Initializes plugin services and registers SSO-related hooks.
     *
     * Settings remain available when forced SSO is disabled. Authentication,
     * custom user management, and password restrictions are registered only
     * when SimpleSAMLphp is loaded and forced SSO remains active.
     *
     * @return void
     */
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

        add_action('lost_password', [$this, 'disableFunction']);
        add_action('retrieve_password', [$this, 'disableFunction']);
        add_action('password_reset', [$this, 'disableFunction']);
        add_action('validate_password_reset', [$this, 'disableFunction']);
        add_filter('show_password_fields', '__return_false');

        // Filters whether to bypass the email notification for new user sign-up.
        add_filter('wpmu_signup_user_notification', '__return_false');

        // Notifies a user that their account activation has been successful.
        add_filter('wpmu_welcome_user_notification', '__return_false');

        // Filters whether to show the Add Existing User form on the Multisite Users screen.
        add_filter('show_network_site_users_add_existing_form', '__return_false');

        // Filters whether to show the Add New User form on the Multisite Users screen.
        add_filter('show_network_site_users_add_new_form', '__return_false');

        // Fires before the administration menu loads in the Network Admin.
        // Rendering the Admin page via: Network Admin > Users > Add new
        add_action('network_admin_menu', [__NAMESPACE__ . '\NetworkUsersMenu', 'userNewPage']);

        // Fires before the administration menu loads in the admin.
        // Rendering the Admin page via: Dashboard > Useres > Add new on individual sites.
        add_action('admin_menu', [__NAMESPACE__ . '\UsersMenu', 'userNewPage']);

        // Detect and Distribute User Actions to designated Handlers via the User Class.
        add_action('admin_init', [__NAMESPACE__ . '\Users', 'userNewAction']);

        add_filter('is_rrze_sso_active', '__return_true');
        // Required for Backwards Compatibility
        add_filter('is_fau_websso_active', '__return_true');
    }

    /**
     * Redirects registration requests to the login page.
     *
     * @return void
     */
    public function registerRedirect()
    {
        if ($this->isLoginPage() && isset($_REQUEST['action']) && $_REQUEST['action'] == 'register') {
            wp_redirect(site_url('wp-login.php', 'login'));
            exit;
        }
    }

    /**
     * Redirects the WordPress add-user screen to the custom plugin screen.
     *
     * @return void
     */
    protected function userNewPageRedirect()
    {
        if (is_admin() && $this->isUserNewPage()) {
            wp_redirect('users.php?page=usernew');
            exit;
        }
    }

    /**
     * Determines whether the current administration page is the core add-user screen.
     *
     * @return bool Whether the current page is user-new.php.
     */
    protected function isUserNewPage()
    {
        if (isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], ['user-new.php']);
        }
        return false;
    }

    /**
     * Determines whether the current request targets the WordPress login page.
     *
     * @return bool Whether the current page is wp-login.php.
     */
    protected function isLoginPage()
    {
        if (isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], ['wp-login.php']);
        }
        return false;
    }

    /**
     * Stops execution for password-management features disabled by forced SSO.
     *
     * @return void
     */
    public function disableFunction()
    {
        $output = __("Disabled function.", 'rrze-sso');
        wp_die($output);
    }

    /**
     * Enqueues assets for the SSO settings screen.
     *
     * @param string $hook Current administration page hook suffix.
     * @return void
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
            'rrze-sso-settings',
            plugins_url('build/admin.js', plugin()->getBasename()),
            ['jquery'],
            plugin()->getVersion()
        );
    }
}
