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
     * Settings API section for general SSO options.
     */
    private const SSO_SECTION = 'sso_options_section';

    /**
     * Settings API section for SimpleSAMLphp options.
     */
    private const SIMPLESAML_SECTION = 'simplesaml_options_section';

    /**
     * Name used to store the plugin options.
     *
     * @var string
     */
    protected string $optionName;

    /**
     * Current plugin options.
     *
     * @var \stdClass
     */
    protected \stdClass $options;

    /**
     * Settings API option group and admin page slug.
     *
     * @var string
     */
    protected string $optionGroup;

    /**
     * Available identity providers, keyed by provider identifier.
     *
     * @var array<string, string>
     */
    protected array $identityProviders;

    /**
     * Initializes the settings identifiers, values, and identity providers.
     */
    public function __construct()
    {
        $this->optionGroup = Options::getOptionGroup();
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();

        $identityProviders = simpleSAML()->getIdentityProviders();
        $this->identityProviders = is_array($identityProviders) ? $identityProviders : [];
    }

    /**
     * Registers the hooks needed for the current WordPress installation type.
     *
     * @return void
     */
    public function loaded(): void
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
    public function networkAdminMenu(): void
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
    public function adminMenu(): void
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
    public function networkOptionsPage(): void
    {
        $this->renderOptionsPage(__('SSO', 'rrze-sso'));
    }

    /**
     * Prepares and renders the single-site settings page.
     *
     * @return void
     */
    public function optionsPage(): void
    {
        $this->renderOptionsPage(__('SSO Settings', 'rrze-sso'), 'options.php');
    }

    /**
     * Loads the shared settings page template.
     *
     * @param string $pageTitle  Page heading.
     * @param string $formAction Form submission URL. Empty for the current URL.
     * @return void
     */
    private function renderOptionsPage(string $pageTitle, string $formAction = ''): void
    {
        $page_title = $pageTitle;
        $form_action = $formAction;
        $option_group = $this->optionGroup;

        require dirname(__DIR__) . '/templates/settings/options.php';
    }

    /**
     * Registers the settings, sections, and fields with the Settings API.
     *
     * @return void
     */
    public function adminInit(): void
    {
        if (!is_multisite()) {
            $this->registerSingleSiteSetting();
        }

        $this->registerSsoSettings();

        if (!$this->options->force_sso) {
            return;
        }

        $this->registerSimpleSamlSettings();
    }

    /**
     * Registers the option and its validation callback on single-site installs.
     *
     * @return void
     */
    private function registerSingleSiteSetting(): void
    {
        register_setting(
            $this->optionGroup,
            $this->optionName,
            [$this, 'optionsValidate']
        );
    }

    /**
     * Registers the general SSO settings section and field.
     *
     * @return void
     */
    private function registerSsoSettings(): void
    {
        add_settings_section(
            self::SSO_SECTION,
            false,
            [$this, 'sso_settings_section'],
            $this->optionGroup
        );

        $this->registerSettingsField(
            'force_sso',
            __('SSO Authentication', 'rrze-sso'),
            [$this, 'ssoField'],
            self::SSO_SECTION
        );
    }

    /**
     * Registers the SimpleSAMLphp settings section and fields.
     *
     * @return void
     */
    private function registerSimpleSamlSettings(): void
    {
        add_settings_section(
            self::SIMPLESAML_SECTION,
            false,
            [$this, 'simpleSAMLSettingsSection'],
            $this->optionGroup
        );

        $this->registerSettingsField(
            'simplesaml_include',
            __('Autoload Path', 'rrze-sso'),
            [$this, 'simpleSAMLIncludeField'],
            self::SIMPLESAML_SECTION
        );

        $this->registerSettingsField(
            'simplesaml_auth_source',
            __('Authentication Source', 'rrze-sso'),
            [$this, 'simpleSAMLAuthSourceField'],
            self::SIMPLESAML_SECTION
        );

        if ($this->identityProviders) {
            $this->registerSettingsField(
                'domain_scope',
                __('Identity Provider Domain Scope', 'rrze-sso'),
                [$this, 'domainScopeField'],
                self::SIMPLESAML_SECTION
            );
        }

        $this->registerSettingsField(
            'allowed_user_email_domains',
            __('Allowed User Email Domains', 'rrze-sso'),
            [$this, 'allowedUserEmailDomainsField'],
            self::SIMPLESAML_SECTION
        );

        $this->registerSettingsField(
            'username_regex_pattern',
            __('Username RegEx Pattern', 'rrze-sso'),
            [$this, 'usernameRegexPattern'],
            self::SIMPLESAML_SECTION
        );
    }

    /**
     * Registers one field on the plugin settings page.
     *
     * @param string   $id       Field identifier.
     * @param string   $title    Field label.
     * @param callable $callback Field rendering callback.
     * @param string   $section  Settings section identifier.
     * @return void
     */
    private function registerSettingsField(
        string $id,
        string $title,
        callable $callback,
        string $section
    ): void {
        add_settings_field(
            $id,
            $title,
            $callback,
            $this->optionGroup,
            $section
        );
    }

    /**
     * Renders the introductory content for the general SSO section.
     *
     * @return void
     */
    public function sso_settings_section(): void
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
    public function ssoField(): void
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
    public function simpleSAMLSettingsSection(): void
    {
        echo '<h3 class="title">' . __("SimpleSAMLphp", 'rrze-sso') . '</h3>';
        echo '<p>' . __("Service Provider Settings.", 'rrze-sso') . '</p>';
    }

    /**
     * Renders the SimpleSAMLphp autoload path field.
     *
     * @return void
     */
    public function simpleSAMLIncludeField(): void
    {
        echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_include]" value="' . esc_attr($this->options->simplesaml_include) . '">';
        echo '<p class="description">' . __("Relative path starting from the wp-content directory.", 'rrze-sso') . '</p>';
    }

    /**
     * Renders the SimpleSAMLphp authentication source field.
     *
     * @return void
     */
    public function simpleSAMLAuthSourceField(): void
    {
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_auth_source]" value="' . esc_attr($this->options->simplesaml_auth_source) . '">';
    }

    /**
     * Renders a domain scope field for each available identity provider.
     *
     * @return void
     */
    public function domainScopeField(): void
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
    public function allowedUserEmailDomainsField(): void
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
    public function usernameRegexPattern(): void
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
     * @param mixed $input Submitted settings values.
     * @return array<string, mixed> Sanitized settings values.
     */
    public function optionsValidate($input): array
    {
        $input = is_array($input) ? $input : [];
        $forceSso = $this->normalizeForceSso($input['force_sso'] ?? 0);

        $input['force_sso'] = $forceSso;
        $input['simplesaml_include'] = $this->validateSimpleSamlInclude(
            $input['simplesaml_include'] ?? $this->options->simplesaml_include,
            (bool) $forceSso
        );
        $input['simplesaml_auth_source'] = $this->validateSimpleSamlAuthSource(
            $input['simplesaml_auth_source'] ?? $this->options->simplesaml_auth_source,
            (bool) $forceSso
        );
        $input['domain_scope'] = $this->validateDomains(
            $input['identity_provider_domain'] ?? $this->options->domain_scope
        );
        $input['allowed_user_email_domains'] = $this->validateDomains(
            $input['allowed_user_email_domains'] ?? $this->options->allowed_user_email_domains
        );
        $input['username_regex_pattern'] = $this->validateUsernameRegexPattern(
            $input['username_regex_pattern'] ?? $this->options->username_regex_pattern
        );

        unset($input['identity_provider_domain']);

        return $input;
    }

    /**
     * Normalizes the forced SSO option to either zero or one.
     *
     * @param mixed $value Submitted option value.
     * @return int Normalized boolean integer.
     */
    private function normalizeForceSso($value): int
    {
        if (!is_scalar($value)) {
            return 0;
        }

        return absint($value) ? 1 : 0;
    }

    /**
     * Sanitizes and validates the SimpleSAMLphp autoload path.
     *
     * @param mixed $value    Submitted path.
     * @param bool  $required Whether the path is required.
     * @return string Sanitized path.
     */
    private function validateSimpleSamlInclude($value, bool $required): string
    {
        $path = $this->sanitizeTextValue($value);

        if ($required && !$path) {
            add_settings_error(
                $this->optionName,
                'simplesaml_include',
                __('The SimpleSAMLphp autoload file is required.', 'rrze-sso')
            );
        }

        if ($path && !is_file(WP_CONTENT_DIR . '/' . $path)) {
            add_settings_error(
                $this->optionName,
                'simplesaml_include',
                sprintf(
                    /* translators: %s: path to the SimpleSAMLphp autoload file. */
                    __('The SimpleSAMLphp autoload file %s does not exist.', 'rrze-sso'),
                    esc_html($path)
                )
            );
        }

        return $path;
    }

    /**
     * Sanitizes and validates the SimpleSAMLphp authentication source.
     *
     * @param mixed $value    Submitted authentication source.
     * @param bool  $required Whether the authentication source is required.
     * @return string Sanitized authentication source.
     */
    private function validateSimpleSamlAuthSource($value, bool $required): string
    {
        $authSource = $this->sanitizeTextValue($value);

        if ($required && !$authSource) {
            add_settings_error(
                $this->optionName,
                'simplesaml_auth_source',
                __('The SimpleSAMLphp authentication source is required.', 'rrze-sso')
            );
        }

        return $authSource;
    }

    /**
     * Sanitizes an arbitrary scalar value as a single line of text.
     *
     * @param mixed $value Submitted value.
     * @return string Sanitized text, or an empty string for non-scalar values.
     */
    private function sanitizeTextValue($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field(trim((string) $value));
    }

    /**
     * Validates a submitted domain list and removes empty or duplicate values.
     *
     * @param mixed $domains Submitted array or newline-delimited domain list.
     * @return array<int|string, string> Valid domains with their original keys.
     */
    private function validateDomains($domains): array
    {
        if (!is_array($domains)) {
            $domains = is_scalar($domains) ? explode(PHP_EOL, (string) $domains) : [];
        }

        $validDomains = [];
        foreach ($domains as $key => $domain) {
            if (!is_scalar($domain)) {
                continue;
            }

            $domain = $this->validateDomain((string) $domain);
            if ($domain) {
                $validDomains[$key] = $domain;
            }
        }

        return array_unique($validDomains);
    }

    /**
     * Normalizes and validates the optional username regular expression.
     *
     * @param mixed $value Submitted regular expression.
     * @return string Normalized regular expression.
     */
    private function validateUsernameRegexPattern($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $pattern = preg_replace('/\s+/', '', (string) $value);
        $pattern = preg_replace('/\\\\+/', '\\', $pattern ?? '');

        if ($pattern && !$this->isValidRegex($pattern)) {
            add_settings_error(
                $this->optionName,
                'username_regex_pattern',
                __('The username regex pattern is invalid.', 'rrze-sso')
            );
        }

        return $pattern ?? '';
    }

    /**
     * Validates and normalizes a domain name.
     *
     * @param string $input Submitted domain name.
     * @return string The trimmed domain, or an empty string when invalid.
     */
    protected function validateDomain(string $input): string
    {
        $domain = trim($input);

        if (!$domain) {
            return '';
        }

        $pattern = '/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (preg_match($pattern, $domain) === 1) {
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
    public function settingsUpdate(): void
    {
        if (empty($_POST[$this->optionName]) || !current_user_can('manage_network_options')) {
            return;
        }

        check_admin_referer($this->optionGroup . '-options');

        $submittedOptions = wp_unslash($_POST[$this->optionName]);
        $input = $this->optionsValidate($submittedOptions);

        update_site_option($this->optionName, $input);
        $this->options = Options::getOptions();

        add_action('network_admin_notices', [$this, 'settingsUpdateNotice']);
    }

    /**
     * Renders the notice shown after network settings are saved.
     *
     * @return void
     */
    public function settingsUpdateNotice(): void
    {
        printf(
            '<div class="notice updated"><p>%s</p></div>',
            esc_html__('Settings saved.', 'rrze-sso')
        );
    }

    /**
     * Determines whether a PCRE pattern is syntactically valid.
     *
     * @param string $pattern Regular expression pattern, including delimiters.
     * @return bool Whether the pattern is valid.
     */
    public function isValidRegex(string $pattern): bool
    {
        set_error_handler(static function (): bool {
            return true;
        }, E_WARNING);

        try {
            return preg_match($pattern, '') !== false
                && preg_last_error() === PREG_NO_ERROR;
        } finally {
            restore_error_handler();
        }
    }
}
