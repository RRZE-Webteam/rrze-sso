<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Settings
{
    /**
     * Option name.
     * @var string
     */
    protected $optionName;

    /**
     * Options object.
     * @var object
     */
    protected $options;

    /**
     * Menu page slug.
     * @var string
     */
    protected $menuPage = 'sso';

    /**
     * Identity providers list.
     * @var array
     */
    protected $identityProviders;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();

        $this->identityProviders = simpleSAML()->getIdentityProviders();
    }

    public function loaded()
    {
        if (is_multisite()) {
            add_action('admin_init', [$this, 'settingsUpdate']);
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
        add_submenu_page(
            'settings.php',
            __('SSO', 'rrze-sso'),
            __('SSO', 'rrze-sso'),
            'manage_network_options',
            $this->menuPage,
            [$this, 'networkOptionsPage']
        );
    }

    /**
     * Add settings page.
     * @return void
     */
    public function adminMenu()
    {
        add_options_page(
            __('SSO', 'rrze-sso'),
            __('SSO', 'rrze-sso'),
            'manage_options',
            $this->menuPage,
            [$this, 'optionsPage']
        );
    }

    /**
     * Network admin menu.
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
     * Admin menu.
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
     * Admin script is being initialized.
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
            if (!empty($this->identityProviders)) {
                add_settings_field('domain_scope', __("Identity Provider Domain Scope", 'rrze-sso'), [$this, 'domainScopeField'], $this->menuPage, 'simplesaml_options_section');
            }
            add_settings_field('allowed_user_email_domains', __("Allowed User Email Domains", 'rrze-sso'), [$this, 'allowedUserEmailDomainsField'], $this->menuPage, 'simplesaml_options_section');
        }
    }

    /**
     * SSO settings section.
     * @return void
     */
    public function sso_settings_section()
    {
        echo '<h3 class="title">' . __("Single Sign-On", 'rrze-sso') . '</h3>';
        echo '<p>' . __("General SSO Settings.", 'rrze-sso') . '</p>';
    }

    /**
     * SSO field.
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
     * SAML settings section.
     * @return void
     */
    public function simpleSAMLSettingsSection()
    {
        echo '<h3 class="title">' . __("SimpleSAMLphp", 'rrze-sso') . '</h3>';
        echo '<p>' . __("Service Provider Settings.", 'rrze-sso') . '</p>';
    }

    /**
     * SAML autoload path field.
     * @return void
     */
    public function simpleSAMLIncludeField()
    {
        echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_include]" value="' . esc_attr($this->options->simplesaml_include) . '">';
        echo '<p class="description">' . __("Relative path starting from the wp-content directory.", 'rrze-sso') . '</p>';
    }

    /**
     * Authentication source field.
     * @return void
     */
    public function simpleSAMLAuthSourceField()
    {
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_auth_source]" value="' . esc_attr($this->options->simplesaml_auth_source) . '">';
    }

    /**
     * Domain scope field.
     * @return void
     */
    public function domainScopeField()
    {
        foreach ($this->identityProviders as $key => $value) {
            $key = sanitize_title($key);
            $domain = $this->options->domain_scope[$key] ?? '';
            echo '<p><strong>', $value, '</strong></p>';
            echo '<input type="hidden" name="identity_providers[]" value="' . $key . '">';
            echo '<input type="text" id="' . $key . '" class="identity-provider-domain regular-text" ';
            echo 'name="' . $this->optionName . '[identity_provider_domain][' . $key . ']" value="' . esc_attr($domain) . '">';
            echo '<p class="description">' . __('(Optional) The domain to add to the username to associate it with the identity provider.', 'rrze-sso') . '</p>';  
        }
    }

    /**
     * Allowed user email domains field.
     * @return void
     */
    public function allowedUserEmailDomainsField()
    {
        $allowedUserEmailDomains = implode(PHP_EOL, (array) $this->options->allowed_user_email_domains);
        echo '<textarea rows="5" cols="55" id="allowed_user_email_domains" class="regular-text" name="' . $this->optionName . '[allowed_user_email_domains]">' . esc_attr($allowedUserEmailDomains) . '</textarea>';
        echo '<p class="description">' . __('List of allowed domains for user email addresses.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('If the field is left empty then all email domains are allowed.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('Format: <i>domain.tld</i>', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('Enter one email domain per line.', 'rrze-sso') . '</p>';
    }

    /**
     * Validate settings options.
     * @param  array $input [description]
     * @return array        [description]
     */
    public function optionsValidate($input)
    {
        $input['force_sso'] = isset($input['force_sso']) && in_array(absint($input['force_sso']), [0, 1]) ? absint($input['force_sso']) : $this->options->force_sso;
        $input['simplesaml_include'] = !empty($input['simplesaml_include']) ? esc_attr(trim($input['simplesaml_include'])) : $this->options->simplesaml_include;
        $input['simplesaml_auth_source'] = isset($input['simplesaml_auth_source']) ? esc_attr(trim($input['simplesaml_auth_source'])) : $this->options->simplesaml_auth_source;
        if ($this->options->force_sso) {
            $identityProvidersDomains = $input['identity_provider_domain'] ?? '';
            $input['domain_scope'] = is_array($identityProvidersDomains) ? array_filter(array_map([__CLASS__, 'sanitizeDomainScope'], $identityProvidersDomains)) : $this->options->domain_scope;
            $emailDomains = $input['allowed_user_email_domains'] ?? '';
            $input['allowed_user_email_domains'] = array_filter(array_map([__CLASS__, 'sanitizeEmailDomain'], explode(PHP_EOL, $emailDomains)));
        } else {
            $input['domain_scope'] = $this->options->domain_scope;
            $input['allowed_user_email_domains'] = $this->options->allowed_user_email_domains;
        }
        if (isset($input['identity_provider_domain'])) {
            unset($input['identity_provider_domain']);
        }
        return $input;
    }

    /**
     * Sanitize a domain scope.
     * @param string $domain
     * @return sting The sanitized domain.
     */
    protected function sanitizeDomainScope($domain)
    {
        $domain = preg_replace('/[^0-9\-.a-z]/', '', strtolower($domain));
        return $domain;
    }

    /**
     * Sanitize an email domain.
     * @param string $domain
     * @return sting The sanitized domain.
     */
    protected function sanitizeEmailDomain($domain)
    {
        return preg_replace('/[^0-9\-.a-z]/', '', strtolower($domain));
    }

    /**
     * Network settings admin notices.
     * @return void
     */
    public function settingsUpdate()
    {
        if (!empty($_POST[$this->optionName])) {
            check_admin_referer($this->menuPage . '-options');
            $input = $this->optionsValidate($_POST[$this->optionName]);
            update_site_option($this->optionName, $input);
            $this->options = Options::getOptions();
            add_action('network_admin_notices', [$this, 'settingsUpdateNotice']);
        }
    }

    /**
     * Settings admin notice.
     * @return void
     */
    public function settingsUpdateNotice()
    {
        $class = 'notice updated';
        $message = __("Settings saved.", 'rrze-sso');

        printf('<div class="%1s"><p>%2s</p></div>', esc_attr($class), esc_html($message));
    }
}
