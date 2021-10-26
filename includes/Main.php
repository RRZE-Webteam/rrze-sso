<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Main
{
    /**
     * [protected description]
     * @var object
     */
    protected $options;

    /**
     * [__construct description]
     * @param string $pluginFile [description]
     */
    public function __construct()
    {
        $this->options = Options::getOptions();
    }

    public function onLoaded()
    {
        $settings = new Settings();
        $settings->onLoaded();

        if (is_super_admin()) {
            $userList = new UsersList();
            $userList->onLoaded();
        }

        if (!$this->options->force_sso) {
            return;
        }

        $simplesaml = new SimpleSAML();
        $simplesaml = $simplesaml->onLoaded();
        if ($simplesaml === false) {
            return;
        }

        $authenticate = new Authenticate($simplesaml);
        $authenticate->onLoaded();

        $this->registerRedirect();
        $this->userNewPageRedirect();

        // Fires before the lost password form (die).
        add_action('lost_password', [$this, 'disableFunction']);
        // Fires before a new password is retrieved (die).
        add_action('retrieve_password', [$this, 'disableFunction']);
        // Fires before the user’s password is reset (die).
        add_action('password_reset', [$this, 'disableFunction']);

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

        add_action('network_admin_menu', [__NAMESPACE__ . '\NetworkMenu', 'userNewPage']);
        add_action('admin_menu', [__NAMESPACE__ . '\StdMenu', 'userNewPage']);

        add_action('admin_init', [__NAMESPACE__ . '\Users', 'userNewAction']);

        add_filter('is_rrze_sso_active', '__return_true');
    }

    public function beforeSignupHeader()
    {
        wp_redirect(site_url('', 'https'));
        exit;
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
            return in_array($GLOBALS['pagenow'], array('user-new.php'));
        }
        return false;
    }

    protected function isLoginPage()
    {
        if (isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], array('wp-login.php'));
        }
        return false;
    }

    public function disableFunction()
    {
        $output = __("Disabled function.", 'rrze-sso');
        wp_die($output);
    }
}
