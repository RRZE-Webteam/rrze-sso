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

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsErrors = array();
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->justReturn(array());
        $simpleSaml = Mockery::mock(SimpleSAML::class);
        $simpleSaml->shouldReceive('getIdentityProviders')->zeroOrMoreTimes()->andReturn(array());
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($simpleSaml);
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('__')->returnArg();
        Functions\when('absint')->alias(static fn($value): int => abs((int) $value));
        Functions\when('sanitize_text_field')->alias(static fn(string $value): string => trim(strip_tags($value)));
        Functions\when('esc_html')->returnArg();
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
}
