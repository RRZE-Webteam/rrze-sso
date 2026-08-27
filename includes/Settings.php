<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Registers, renders, and validates the plugin settings.
 *
 * Supports both single-site and multisite installations. In multisite, the
 * settings are stored as network options and submitted through the Network
 * Admin settings page.
 */
class Settings
{
    /**
     * Name used to store the plugin options.
     *
     * @var string
     */
    protected $optionName;

    /**
     * Current plugin options.
     *
     * @var \stdClass
     */
    protected $options;

    /**
     * Settings API option group and admin page slug.
     *
     * @var string
     */
    protected $optionGroup;

    /**
     * Available identity providers, keyed by provider identifier.
     *
     * @var array<string, string>
     */
    protected $identityProviders;

    /**
     * Initializes the settings identifiers, values, and identity providers.
     */
    public function __construct()
    {
        $this->optionGroup = Options::getOptionGroup();
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();

        $this->identityProviders = simpleSAML()->getIdentityProviders();
    }

    /**
     * Registers the hooks needed for the current WordPress installation type.
     *
     * @return void
     */
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
     * Adds the SSO settings page to the Network Admin settings menu.
     *
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
     * Adds the SSO settings page to the single-site settings menu.
     *
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
     * Prepares and renders the Network Admin settings page.
     *
     * @return void
     */
    public function networkOptionsPage()
    {
        $page_title = __('SSO', 'rrze-sso');
        $form_action = '';
        $option_group = $this->optionGroup;

        require dirname(__DIR__) . '/templates/settings/options.php';
    }

    /**
     * Prepares and renders the single-site settings page.
     *
     * @return void
     */
    public function optionsPage()
    {
        $page_title = __('SSO Settings', 'rrze-sso');
        $form_action = 'options.php';
        $option_group = $this->optionGroup;

        require dirname(__DIR__) . '/templates/settings/options.php';
    }

    /**
     * Registers the settings, sections, and fields with the Settings API.
     *
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
     * Renders the introductory content for the general SSO section.
     *
     * @return void
     */
    public function sso_settings_section()
    {
        echo '<h3 class="title">' . __("Single Sign-On", 'rrze-sso') . '</h3>';
        echo '<p>' . __("General SSO Settings.", 'rrze-sso') . '</p>';
        settings_errors($this->optionName);
    }

    /**
     * Renders the control used to enable or disable forced SSO.
     *
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
     * Renders the introductory content for the SimpleSAMLphp section.
     *
     * @return void
     */
    public function simpleSAMLSettingsSection()
    {
        echo '<h3 class="title">' . __("SimpleSAMLphp", 'rrze-sso') . '</h3>';
        echo '<p>' . __("Service Provider Settings.", 'rrze-sso') . '</p>';
    }

    /**
     * Renders the SimpleSAMLphp autoload path field.
     *
     * @return void
     */
    public function simpleSAMLIncludeField()
    {
        echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_include]" value="' . esc_attr($this->options->simplesaml_include) . '">';
        echo '<p class="description">' . __("Relative path starting from the wp-content directory.", 'rrze-sso') . '</p>';
    }

    /**
     * Renders the SimpleSAMLphp authentication source field.
     *
     * @return void
     */
    public function simpleSAMLAuthSourceField()
    {
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_auth_source]" value="' . esc_attr($this->options->simplesaml_auth_source) . '">';
    }

    /**
     * Renders a domain scope field for each available identity provider.
     *
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
     * Renders the list of allowed user email domains.
     *
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
     * Renders the optional username regular expression field.
     *
     * @return void
     */
    public function usernameRegexPattern()
    {
        $usernameRegexPattern = $this->options->username_regex_pattern;
        echo '<input type="text" id="rrze-sso-username-regex-pattern" class="regular-text" name="' . $this->optionName . '[username_regex_pattern]" value="' . esc_attr($usernameRegexPattern) . '">';
        echo '<p class="description">' . __('Regex pattern to allow extra characters in the username.', 'rrze-sso') . '</p>';
    }

    /**
     * Sanitizes and validates submitted plugin settings.
     *
     * Adds Settings API errors for invalid required values, domains, and
     * regular expressions.
     *
     * @param array<string, mixed> $input Submitted settings values.
     * @return array<string, mixed> Sanitized settings values.
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
     * Validates and normalizes a domain name.
     *
     * @param string $input Submitted domain name.
     * @return string The trimmed domain, or an empty string when invalid.
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
     * Processes and persists a submitted Network Admin settings form.
     *
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
     * Renders the notice shown after network settings are saved.
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
     * Determines whether a PCRE pattern is syntactically valid.
     *
     * @param string $pattern Regular expression pattern, including delimiters.
     * @return bool Whether the pattern is valid.
     */
    public function isValidRegex(string $pattern): bool
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
