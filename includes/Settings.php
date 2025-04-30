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
     * Option group (menu page slug).
     * @var string
     */
    protected $optionGroup;

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
        $this->optionGroup = Options::getOptionGroup();
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
            $this->optionGroup,
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
            $this->optionGroup,
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
                <?php do_settings_sections($this->optionGroup); ?>
                <?php settings_fields($this->optionGroup); ?>
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
                <?php do_settings_sections($this->optionGroup); ?>
                <?php settings_fields($this->optionGroup); ?>
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
            register_setting(
                $this->optionGroup,
                $this->optionName,
                [$this, 'optionsValidate']
            );
        }

        add_settings_section(
            'sso_options_section',
            false,
            [$this, 'sso_settings_section'],
            $this->optionGroup
        );

        add_settings_field(
            'force_sso',
            __("SSO Authentication", 'rrze-sso'),
            [$this, 'ssoField'],
            $this->optionGroup,
            'sso_options_section'
        );

        if ($this->options->force_sso) {
            add_settings_section(
                'simplesaml_options_section',
                false,
                [$this, 'simpleSAMLSettingsSection'],
                $this->optionGroup
            );

            add_settings_field(
                'simplesaml_include',
                __("Autoload Path", 'rrze-sso'),
                [$this, 'simpleSAMLIncludeField'],
                $this->optionGroup,
                'simplesaml_options_section'
            );

            add_settings_field(
                'simplesaml_auth_source',
                __("Authentication Source", 'rrze-sso'),
                [$this, 'simpleSAMLAuthSourceField'],
                $this->optionGroup,
                'simplesaml_options_section'
            );

            if (!empty($this->identityProviders)) {
                add_settings_field(
                    'domain_scope',
                    __("Identity Provider Domain Scope", 'rrze-sso'),
                    [$this, 'domainScopeField'],
                    $this->optionGroup,
                    'simplesaml_options_section'
                );
            }

            add_settings_field(
                'allowed_user_email_domains',
                __("Allowed User Email Domains", 'rrze-sso'),
                [$this, 'allowedUserEmailDomainsField'],
                $this->optionGroup,
                'simplesaml_options_section'
            );

            add_settings_field(
                'username_regex_pattern',
                __('Username RegEx Pattern', 'rrze-sso'),
                [$this, 'usernameRegexPattern'],
                $this->optionGroup,
                'simplesaml_options_section'
            );
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
        settings_errors($this->optionName);
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
            echo '<p class="description">' . __('(Optional) The domain to add to the user identifier to associate it with the identity provider.', 'rrze-sso') . '</p>';
        }
    }

    /**
     * Allowed user email domains field.
     * @return void
     */
    public function allowedUserEmailDomainsField()
    {
        $allowedUserEmailDomains = implode(PHP_EOL, (array) $this->options->allowed_user_email_domains);
        echo '<textarea rows="5" cols="55" id="rrze-sso-allowed-user-email-domains" class="regular-text" name="' . $this->optionName . '[allowed_user_email_domains]">' . esc_attr($allowedUserEmailDomains) . '</textarea>';
        echo '<p class="description">' . __('List of allowed domains for user email addresses.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('If the field is left empty then all email domains are allowed.', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('Format: <i>domain.tld</i>', 'rrze-sso') . '</p>';
        echo '<p class="description">' . __('Enter one email domain per line.', 'rrze-sso') . '</p>';
    }

    /**
     * Username regex pattern field.
     * @return void
     */
    public function usernameRegexPattern()
    {
        $usernameRegexPattern = $this->options->username_regex_pattern;
        echo '<input type="text" id="rrze-sso-username-regex-pattern" class="regular-text" name="' . $this->optionName . '[username_regex_pattern]" value="' . esc_attr($usernameRegexPattern) . '">';
        echo '<p class="description">' . __('Regex pattern to allow extra characters in the username.', 'rrze-sso') . '</p>';
    }

    /**
     * Validate settings options.
     * @param  array $input [description]
     * @return array        [description]
     */
    public function optionsValidate($input)
    {
        $forceSso = $input['force_sso'] ?? 0;
        $forceSso = absint($forceSso);
        $input['force_sso'] = $forceSso ? 1 : 0;

        $simplesamlInclude = $input['simplesaml_include'] ?? $this->options->simplesaml_include;
        $simplesamlInclude = sanitize_text_field(trim($simplesamlInclude));
        if ($forceSso && empty($simplesamlInclude)) {
            add_settings_error(
                $this->optionName,
                'simplesaml_include',
                __('The SimpleSAMLphp autoload file is required.', 'rrze-sso')
            );
        }
        if ($simplesamlInclude && !is_file(WP_CONTENT_DIR . '/' . $simplesamlInclude)) {
            add_settings_error(
                $this->optionName,
                'simplesaml_include',
                sprintf(
                    /* translators: %s: path to the SimpleSAMLphp autoload file. */
                    __('The SimpleSAMLphp autoload file %s does not exist.', 'rrze-sso'),
                    esc_html($input['simplesaml_include'])
                )
            );
        }
        $input['simplesaml_include'] = $simplesamlInclude;

        $simplesamlAuthSource = $input['simplesaml_auth_source'] ?? $this->options->simplesaml_auth_source;
        $simplesamlAuthSource = sanitize_text_field(trim($simplesamlAuthSource));
        $input['simplesaml_auth_source'] = $simplesamlAuthSource;
        if ($forceSso && empty($simplesamlAuthSource)) {
            add_settings_error(
                $this->optionName,
                'simplesaml_auth_source',
                __('The SimpleSAMLphp authentication source is required.', 'rrze-sso')
            );
        }

        foreach ($this->identityProviders as $key => $value) {
            $key = sanitize_title($key);
            if (isset($input['identity_provider_domain'][$key])) {
                $domain = $input['identity_provider_domain'][$key];
                if (!$this->validateDomain($domain)) {
                    unset($input['identity_provider_domain'][$key]);
                }
            }
        }
        $domainScope = $input['identity_provider_domain'] ?? $this->options->domain_scope;
        $domainScope = is_array($domainScope) ? $domainScope : [];
        $domainScope = array_map(
            [__CLASS__, 'validateDomain'],
            $domainScope
        );
        $domainScope = array_filter($domainScope);
        $input['domain_scope'] = array_unique($domainScope);

        $emailDomains = $input['allowed_user_email_domains'] ?? $this->options->allowed_user_email_domains;
        $emailDomains = is_array($emailDomains) ? $emailDomains : explode(PHP_EOL, $input['allowed_user_email_domains']);
        $emailDomains = array_map(
            [__CLASS__, 'validateDomain'],
            $emailDomains
        );
        $emailDomains = array_filter($emailDomains);
        $input['allowed_user_email_domains'] = array_unique($emailDomains);

        $usernameRegexPattern = $input['username_regex_pattern'] ?? $this->options->username_regex_pattern;
        if ($usernameRegexPattern) {
            $usernameRegexPattern = preg_replace('/\s+/', '', $usernameRegexPattern);
            $usernameRegexPattern = preg_replace('/\\\\+/', '\\', $usernameRegexPattern);
            if (!$this->isValidRegex($usernameRegexPattern)) {
                add_settings_error(
                    $this->optionName,
                    'username_regex_pattern',
                    __('The username regex pattern is invalid.', 'rrze-sso'),
                );
            }
            $input['username_regex_pattern'] = $usernameRegexPattern;
        }

        // Remove the identity provider domain from the input array
        // to avoid saving it in the database.
        if (isset($input['identity_provider_domain'])) {
            unset($input['identity_provider_domain']);
        }

        return $input;
    }

    /**
     * Validate a domain name.
     * @param string $input Entered domain name.
     * @return string
     */
    protected function validateDomain(string $input): string
    {
        if (!$domain = trim($input)) {
            return $domain;
        }
        $pattern = '/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (preg_match($pattern, $domain)) {
            return $domain;
        }
        add_settings_error(
            $this->optionName,
            'domain_scope',
            sprintf(
                /* translators: %s: domain name. */
                __('%s is not a valid domain name.', 'rrze-sso'),
                esc_html($domain)
            )
        );
        return '';
    }

    /**
     * Network settings admin notices.
     * @return void
     */
    public function settingsUpdate()
    {
        if (!empty($_POST[$this->optionName])) {
            check_admin_referer($this->optionGroup . '-options');
            $input = $this->optionsValidate($_POST[$this->optionName]);
            update_site_option($this->optionName, $input);
            $this->options = Options::getOptions();
            add_action('network_admin_notices', [$this, 'settingsUpdateNotice']);
        }
    }

    /**
     * Settings admin notice.
     * 
     * @return void
     */
    public function settingsUpdateNotice()
    {
        $class = 'notice updated';
        $message = __("Settings saved.", 'rrze-sso');

        printf('<div class="%1s"><p>%2s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Checks whether a given PCRE pattern is syntactically valid.
     *
     * @param string $pattern  The regex pattern, e.g. '/^[a-z]+$/i'
     * @return bool            True if the pattern is valid; false otherwise
     */
    function isValidRegex(string $pattern): bool
    {
        // Temporarily install a no-op error handler to suppress E_WARNING
        set_error_handler(function () {}, E_WARNING);

        // Try to run preg_match with an empty subject
        $result = preg_match($pattern, '');
        // Capture the last PCRE error code
        $errorCode = preg_last_error();

        // Restore the previous error handler
        restore_error_handler();

        // Return true only if preg_match didn't return false and no PCRE error was reported
        return ($result !== false) && ($errorCode === PREG_NO_ERROR);
    }
}
