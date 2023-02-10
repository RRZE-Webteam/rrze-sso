<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Settings
{
    /**
     * [protected description]
     * @var string
     */
    protected $optionName;

    /**
     * [protected description]
     * @var object
     */
    protected $options;

    /**
     * [protected description]
     * @var string
     */
    protected $menuPage = 'sso';

    /**
     * [__construct description]
     */
    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();
    }

    public function onLoaded()
    {
        if (is_multisite()) {
            add_action('admin_init', [$this, 'networkSettingsUpdate']);
            add_action('network_admin_menu', [$this, 'networkAdminMenu']);
        } else {
            add_action('admin_menu', [$this, 'adminMenu']);
        }

        add_action('admin_init', [$this, 'adminInit']);
    }

    /**
     * [networkAdminMenu description]
     * @return void
     */
    public function networkAdminMenu()
    {
        add_submenu_page('settings.php', __('SSO', 'rrze-sso'), __('SSO', 'rrze-sso'), 'manage_network_options', $this->menuPage, [$this, 'networkOptionsPage']);
    }

    /**
     * [adminMenu description]
     * @return void
     */
    public function adminMenu()
    {
        add_options_page(__('SSO', 'rrze-sso'), __('SSO', 'rrze-sso'), 'manage_options', $this->menuPage, [$this, 'optionsPage']);
    }

    /**
     * [networkOptionsPage description]
     * @return void
     */
    public function networkOptionsPage()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html(__('SSO', 'rrze-sso')); ?></h1>
            <form method="post">
                <?php do_settings_sections($this->menuPage); ?>
                <?php settings_fields($this->menuPage); ?>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

    /**
     * [optionsPage description]
     * @return void
     */
    public function optionsPage()
    {
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(__("SSO Settings", 'rrze-sso')); ?></h1>
            <form method="post" action="options.php">
                <?php do_settings_sections($this->menuPage); ?>
                <?php settings_fields($this->menuPage); ?>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    /**
     * [adminInit description]
     * @return void
     */
    public function adminInit()
    {
        if (!is_multisite()) {
            register_setting($this->menuPage, $this->optionName, [$this, 'optionsValidate']);
        }

        add_settings_section('sso_options_section', false, [$this, 'sso_settings_section'], $this->menuPage);
        add_settings_field('force_sso', __("SSO Authentication", 'rrze-sso'), [$this, 'ssoField'], $this->menuPage, 'sso_options_section');

        add_settings_section('simplesaml_options_section', false, [$this, 'simpleSAMLSettingsSection'], $this->menuPage);
        add_settings_field('simplesaml_include', __("Autoload Path", 'rrze-sso'), [$this, 'simpleSAMLIncludeField'], $this->menuPage, 'simplesaml_options_section');
        add_settings_field('simplesaml_auth_source', __("Authentication Source", 'rrze-sso'), [$this, 'simpleSAMLAuthSourceField'], $this->menuPage, 'simplesaml_options_section');
        if ($this->options->force_sso) {
            add_settings_field('domain_scope', __("Domain Scope", 'rrze-sso'), [$this, 'domainScopeField'], $this->menuPage, 'simplesaml_options_section');
            add_settings_field('allowed_user_email_domains', __("Allowed User Email Domains", 'rrze-sso'), [$this, 'allowedUserEmailDomainsField'], $this->menuPage, 'simplesaml_options_section');
        }
    }

    /**
     * [sso_settings_section description]
     * @return void
     */
    public function sso_settings_section()
    {
        echo '<h3 class="title">' . __("Single Sign-On", 'rrze-sso') . '</h3>';
        echo '<p>' . __("General SSO Settings.", 'rrze-sso') . '</p>';
    }

    /**
     * [ssoField description]
     * @return void
     */
    public function ssoField()
    {
        echo '<fieldset>';
        echo '<legend class="screen-reader-text">' . __("SSO Settings", 'rrze-sso') . '</legend>';
        echo '<label><input name="' . $this->optionName . '[force_sso]" id="force_sso0" value="0" type="radio" ', checked($this->options->force_sso, 0), '> ' . __("Disabled", 'rrze-sso') . '</label><br>';
        echo '<label><input name="' . $this->optionName . '[force_sso]" id="force_sso1" value="1" type="radio" ', checked($this->options->force_sso, 1), '> ' . __("Enabled", 'rrze-sso') . '</label><br>';
        echo '</fieldset>';
    }

    /**
     * [simpleSAMLSettingsSection description]
     * @return void
     */
    public function simpleSAMLSettingsSection()
    {
        echo '<h3 class="title">' . __("SimpleSAMLphp", 'rrze-sso') . '</h3>';
        echo '<p>' . __("Service Provider Settings.", 'rrze-sso') . '</p>';
    }

    /**
     * [simpleSAMLIncludeField description]
     * @return void
     */
    public function simpleSAMLIncludeField()
    {
        echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_include]" value="' . esc_attr($this->options->simplesaml_include) . '">';
        echo '<p class="description">' . __("Relative path starting from the wp-content directory.", 'rrze-sso') . '</p>';
    }

    /**
     * [simpleSAMLAuthSourceField description]
     * @return void
     */
    public function simpleSAMLAuthSourceField()
    {
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_auth_source]" value="' . esc_attr($this->options->simplesaml_auth_source) . '">';
    }

    /**
     * [domainScopeField description]
     * @return void
     */
    public function domainScopeField()
    {
        $domainScope = implode(PHP_EOL, (array) $this->options->domain_scope);
        echo '<textarea rows="5" cols="55" id="domain_scope" class="regular-text" name="' . $this->optionName . '[domain_scope]">' . esc_attr($domainScope) . '</textarea>';
        echo '<p class="description">' . __('List of domains to be added as suffix to the username.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('The domain suffix can have an alias. Use the ">" separator to indicate the alias.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('If the field is left empty or a certain domain is not found in the list then the domain suffix will not be used.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('Enter one domain per line.', 'rrze-sso') . '</p>';
    }

    /**
     * [allowedUserEmailDomainsField description]
     * @return void
     */
    public function allowedUserEmailDomainsField()
    {
        $allowedUserEmailDomains = implode(PHP_EOL, (array) $this->options->allowed_user_email_domains);
        echo '<textarea rows="5" cols="55" id="allowed_user_email_domains" class="regular-text" name="' . $this->optionName . '[allowed_user_email_domains]">' . esc_attr($allowedUserEmailDomains) . '</textarea>';
        echo '<p class="description">' . __('List of allowed domains for user email addresses.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('If the field is left empty then all email domains are allowed.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('Enter one email domain per line.', 'rrze-sso') . '</p>';
    }

    /**
     * [optionsValidate description]
     * @param  array $input [description]
     * @return array        [description]
     */
    public function optionsValidate($input)
    {
        $input['force_sso'] = isset($input['force_sso']) && in_array(absint($input['force_sso']), [0, 1]) ? absint($input['force_sso']) : $this->options->force_sso;

        $input['simplesaml_include'] = !empty($input['simplesaml_include']) ? esc_attr(trim($input['simplesaml_include'])) : $this->options->simplesaml_include;
        $input['simplesaml_auth_source'] = isset($input['simplesaml_auth_source']) ? esc_attr(trim($input['simplesaml_auth_source'])) : $this->options->simplesaml_auth_source;
        if ($this->options->force_sso) {
            $input['allowed_user_email_domains'] = array_filter(array_map('trim', explode(PHP_EOL, $input['allowed_user_email_domains'])));
            $input['domain_scope'] = array_filter(array_map('trim', explode(PHP_EOL, $input['domain_scope'])));
        } else {
            $input['allowed_user_email_domains'] = $this->options->allowed_user_email_domains;
            $input['domain_scope'] = $this->options->domain_scope;
        }

        return $input;
    }

    /**
     * [networkSettingsUpdate description]
     * @return void
     */
    public function networkSettingsUpdate()
    {
        if (!empty($_POST[$this->optionName])) {
            check_admin_referer($this->menuPage . '-options');
            $input = $this->optionsValidate($_POST[$this->optionName]);
            update_site_option($this->optionName, $input);
            $this->options = Options::getOptions();
            add_action('network_admin_notices', [$this, 'networkSettingsUpdateNotice']);
        }
    }

    /**
     * [networkSettingsUpdateNotice description]
     * @return void
     */
    public function networkSettingsUpdateNotice()
    {
        $class = 'notice updated';
        $message = __("Settings saved.", 'rrze-sso');

        printf('<div class="%1s"><p>%2s</p></div>', esc_attr($class), esc_html($message));
    }
}
