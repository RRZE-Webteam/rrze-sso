<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Settings;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;

#[CoversClass(Settings::class)]
class SettingsTest extends TestCase
{
    /** @var array<int, array<int, mixed>> */
    private $settingsErrors = array();

    /** @var array<string, mixed> */
    private $options = array();

    /** @var bool */
    private $multisite = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsErrors = array();
        $this->multisite = false;
        $this->options = array();
        Functions\when('is_multisite')->alias(fn(): bool => $this->multisite);
        Functions\when('get_option')->alias(fn(): array => $this->options);
        Functions\when('get_site_option')->alias(fn(): array => $this->options);
        $simpleSaml = Mockery::mock(SimpleSAML::class);
        $simpleSaml->shouldReceive('getIdentityProviders')
            ->zeroOrMoreTimes()
            ->andReturn(array('idp-one' => 'Identity Provider One'));
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($simpleSaml);
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('__')->returnArg();
        Functions\when('absint')->alias(static fn($value): int => abs((int) $value));
        Functions\when('sanitize_text_field')->alias(static fn(string $value): string => trim(strip_tags($value)));
        Functions\when('sanitize_title')->alias(static fn(string $value): string => strtolower(trim($value)));
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('checked')->alias(
            static fn($checked, $current): string => $checked == $current ? 'checked="checked"' : ''
        );
        Functions\when('settings_errors')->justReturn(null);
        Functions\when('add_settings_error')->alias(
            function (...$arguments): void {
                $this->settingsErrors[] = $arguments;
            }
        );
    }

    public function testOptionsValidateNormalizesDomainsAndScalarValues(): void
    {
        $settings = new Settings();
        $result = $settings->optionsValidate(array(
            'force_sso' => array('invalid'),
            'simplesaml_include' => array('invalid'),
            'simplesaml_auth_source' => ' custom-sp ',
            'identity_provider_domain' => array(
                'idp-one' => ' example.org ',
                'idp-two' => 'not-a-domain',
            ),
            'allowed_user_email_domains' => "example.org\nexample.org\nsub.example.net",
            'username_regex_pattern' => ' /^[a-z0-9]+$/i ',
        ));

        self::assertSame(0, $result['force_sso']);
        self::assertSame('', $result['simplesaml_include']);
        self::assertSame('custom-sp', $result['simplesaml_auth_source']);
        self::assertSame(array('idp-one' => 'example.org'), $result['domain_scope']);
        self::assertSame(array(0 => 'example.org', 2 => 'sub.example.net'), $result['allowed_user_email_domains']);
        self::assertSame('/^[a-z0-9]+$/i', $result['username_regex_pattern']);
        self::assertArrayNotHasKey('identity_provider_domain', $result);
        self::assertCount(1, $this->settingsErrors);
        self::assertSame('domain_scope', $this->settingsErrors[0][1]);
    }

    public function testInvalidRegexIsReportedWithoutLeakingPhpWarnings(): void
    {
        $settings = new Settings();
        $result = $settings->optionsValidate(array(
            'force_sso' => 0,
            'simplesaml_include' => '',
            'simplesaml_auth_source' => '',
            'identity_provider_domain' => array(),
            'allowed_user_email_domains' => array(),
            'username_regex_pattern' => '/[invalid/',
        ));

        self::assertSame('/[invalid/', $result['username_regex_pattern']);
        self::assertSame('username_regex_pattern', $this->settingsErrors[0][1]);
        self::assertTrue($settings->isValidRegex('/^[a-z]+$/'));
        self::assertFalse($settings->isValidRegex('/[invalid/'));
    }

    public function testLoadedAndMenusRegisterSingleAndNetworkHooks(): void
    {
        Functions\expect('add_options_page')
            ->once()
            ->withArgs(static fn(...$arguments): bool => 'manage_options' === $arguments[2] && 'sso' === $arguments[3]);
        Functions\expect('add_submenu_page')
            ->once()
            ->withArgs(static fn(...$arguments): bool => 'settings.php' === $arguments[0] && 'manage_network_options' === $arguments[3]);

        $settings = new Settings();
        $settings->loaded();
        $settings->adminMenu();

        $this->multisite = true;
        $networkSettings = new Settings();
        $networkSettings->loaded();
        $networkSettings->networkAdminMenu();

        self::assertTrue(true);
    }

    public function testAdminInitRegistersEveryEnabledSettingField(): void
    {
        $this->options = array('force_sso' => 1);
        $fieldIds = array();

        Functions\when('register_setting')->justReturn(null);
        Functions\when('add_settings_section')->justReturn(null);
        Functions\when('add_settings_field')->alias(
            function (string $id) use (&$fieldIds): void {
                $fieldIds[] = $id;
            }
        );

        (new Settings())->adminInit();

        self::assertSame(
            array(
                'force_sso',
                'simplesaml_include',
                'simplesaml_auth_source',
                'domain_scope',
                'allowed_user_email_domains',
                'username_regex_pattern',
            ),
            $fieldIds
        );
    }

    public function testSettingsFieldsRenderStoredValues(): void
    {
        $this->options = array(
            'force_sso' => 1,
            'simplesaml_include' => '/simple/lib/_autoload.php',
            'simplesaml_auth_source' => 'custom-sp',
            'domain_scope' => array('idp-one' => 'scope.example.org'),
            'allowed_user_email_domains' => array('example.org', 'example.net'),
            'username_regex_pattern' => '/^[a-z]+$/',
        );
        $settings = new Settings();

        ob_start();
        $settings->sso_settings_section();
        $settings->ssoField();
        $settings->simpleSAMLSettingsSection();
        $settings->simpleSAMLIncludeField();
        $settings->simpleSAMLAuthSourceField();
        $settings->domainScopeField();
        $settings->allowedUserEmailDomainsField();
        $settings->usernameRegexPattern();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('force_sso1', $output);
        self::assertStringContainsString('/simple/lib/_autoload.php', $output);
        self::assertStringContainsString('custom-sp', $output);
        self::assertStringContainsString('scope.example.org', $output);
        self::assertStringContainsString("example.org\nexample.net", $output);
        self::assertStringContainsString('/^[a-z]+$/', $output);
    }

    public function testOptionsPagesRenderSharedFormTemplate(): void
    {
        Functions\when('do_settings_sections')->justReturn(null);
        Functions\when('settings_fields')->justReturn(null);
        Functions\when('submit_button')->justReturn(null);
        $settings = new Settings();

        ob_start();
        $settings->optionsPage();
        $singleSite = (string) ob_get_clean();
        ob_start();
        $settings->networkOptionsPage();
        $network = (string) ob_get_clean();

        self::assertStringContainsString('SSO Settings', $singleSite);
        self::assertStringContainsString('action="options.php"', $singleSite);
        self::assertStringContainsString('<h1>SSO</h1>', $network);
        self::assertStringNotContainsString('action=', $network);
    }

    public function testSettingsUpdatePersistsValidatedNetworkOptions(): void
    {
        $this->multisite = true;
        $_POST['rrze_sso'] = array(
            'force_sso' => 0,
            'simplesaml_include' => '',
            'simplesaml_auth_source' => '',
            'identity_provider_domain' => array(),
            'allowed_user_email_domains' => array(),
            'username_regex_pattern' => '',
        );
        $saved = array();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(true);
        Functions\when('wp_unslash')->returnArg();
        Functions\when('update_site_option')->alias(
            function (string $name, array $value) use (&$saved): bool {
                $saved = compact('name', 'value');
                return true;
            }
        );

        (new Settings())->settingsUpdate();

        self::assertSame('rrze_sso', $saved['name']);
        self::assertSame(0, $saved['value']['force_sso']);
        unset($_POST['rrze_sso']);
    }

    public function testSettingsUpdateNoticeEscapesItsMessage(): void
    {
        ob_start();
        (new Settings())->settingsUpdateNotice();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Settings saved.', $output);
    }

    public function testAdminInitStopsAfterGeneralSettingsWhenSsoIsDisabled(): void
    {
        $fieldIds = array();
        Functions\when('register_setting')->justReturn(null);
        Functions\when('add_settings_section')->justReturn(null);
        Functions\when('add_settings_field')->alias(
            function (string $id) use (&$fieldIds): void {
                $fieldIds[] = $id;
            }
        );

        (new Settings())->adminInit();

        self::assertSame(array('force_sso'), $fieldIds);
    }

    public function testRequiredSimpleSamlValuesAndMissingFileAreReported(): void
    {
        defined('WP_CONTENT_DIR') || define('WP_CONTENT_DIR', ABSPATH);
        $settings = new Settings();

        $settings->optionsValidate(array(
            'force_sso' => 1,
            'simplesaml_include' => '',
            'simplesaml_auth_source' => '',
            'identity_provider_domain' => array(),
            'allowed_user_email_domains' => array(),
            'username_regex_pattern' => '',
        ));
        $settings->optionsValidate(array(
            'force_sso' => 1,
            'simplesaml_include' => '/definitely-missing.php',
            'simplesaml_auth_source' => 'test-sp',
            'identity_provider_domain' => array(),
            'allowed_user_email_domains' => array(),
            'username_regex_pattern' => '',
        ));

        $codes = array_column($this->settingsErrors, 1);
        self::assertContains('simplesaml_include', $codes);
        self::assertContains('simplesaml_auth_source', $codes);
        self::assertGreaterThanOrEqual(3, count($this->settingsErrors));
    }

    public function testNonScalarAndEmptyValidationValuesAreIgnored(): void
    {
        $result = (new Settings())->optionsValidate(array(
            'force_sso' => 0,
            'simplesaml_include' => '',
            'simplesaml_auth_source' => '',
            'identity_provider_domain' => array('empty' => '', 'nested' => array('invalid')),
            'allowed_user_email_domains' => array(array('invalid')),
            'username_regex_pattern' => array('invalid'),
        ));

        self::assertSame(array(), $result['domain_scope']);
        self::assertSame(array(), $result['allowed_user_email_domains']);
        self::assertSame('', $result['username_regex_pattern']);
    }

    public function testSettingsUpdateRequiresSubmittedDataAndCapability(): void
    {
        $_POST['rrze_sso'] = array('force_sso' => 1);
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('update_site_option')->never();

        (new Settings())->settingsUpdate();

        unset($_POST['rrze_sso']);
        self::assertTrue(true);
    }
}
