<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\UserSignupValidator;
use WP_Error;

#[CoversClass(UserSignupValidator::class)]
class UserSignupValidatorTest extends TestCase
{
    /** @var object|null */
    private $previousWpdb;

    /** @var UserSignupValidatorWpdb */
    private $wpdb;

    /** @var array<string, mixed> */
    private $options = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $this->wpdb = new UserSignupValidatorWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->options = array(
            'domain_scope' => array('idp-one' => 'example.org'),
            'allowed_user_email_domains' => array('example.org'),
            'username_regex_pattern' => '',
        );

        $simpleSaml = Mockery::mock(SimpleSAML::class);
        $simpleSaml->shouldReceive('getIdentityProviders')
            ->zeroOrMoreTimes()
            ->andReturn(array('idp-one' => 'Identity Provider One'));
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($simpleSaml);

        Functions\when('__')->returnArg();
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->alias(fn(): array => $this->options);
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('sanitize_title')->alias(static fn(string $value): string => strtolower(trim($value)));
        Functions\when('wp_strip_all_tags')->alias(static fn(string $value): string => strip_tags($value));
        Functions\when('remove_accents')->returnArg();
        Functions\when('get_site_option')->alias(
            static fn(string $name) => 'illegal_names' === $name ? array('admin', 'root') : array()
        );
        Functions\when('sanitize_email')->alias(static fn(string $value): string => strtolower(trim($value)));
        Functions\when('is_email_address_unsafe')->justReturn(false);
        Functions\when('is_email')->alias(
            static fn(string $email): bool => false !== filter_var($email, FILTER_VALIDATE_EMAIL)
        );
        Functions\when('username_exists')->justReturn(false);
        Functions\when('email_exists')->justReturn(false);
        Functions\when('apply_filters')->alias(static fn(string $hook, $value) => $value);
    }

    protected function tearDown(): void
    {
        if (null === $this->previousWpdb) {
            unset($GLOBALS['wpdb']);
        } else {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        }

        parent::tearDown();
    }

    public function testValidateReturnsNormalizedSignupForKnownIdentityProvider(): void
    {
        $result = UserSignupValidator::validate('idp-one', 'alice', 'ALICE@example.org');

        self::assertSame('alice@idp-one', $result['user_name']);
        self::assertSame('alice', $result['orig_username']);
        self::assertSame('alice@example.org', $result['user_email']);
        self::assertFalse($result['errors']->has_errors());
        self::assertSame(array('alice@idp-one', 'alice@example.org'), $this->wpdb->preparedArguments);
        self::assertSame(1, $this->wpdb->queryCount);
    }

    public function testValidateCollectsProviderUsernameAndEmailErrors(): void
    {
        $result = UserSignupValidator::validate('', '12', 'invalid-address');

        self::assertInstanceOf(WP_Error::class, $result['errors']);
        self::assertContains('user_idp', $result['errors']->get_error_codes());
        self::assertContains('user_name', $result['errors']->get_error_codes());
        self::assertContains('user_email', $result['errors']->get_error_codes());
        self::assertSame('12', $result['user_name']);
    }

    public function testValidateReportsUnknownProviderCustomPatternAndDomainRestrictions(): void
    {
        $this->options['username_regex_pattern'] = '/^[a-z]+$/';
        $this->options['allowed_user_email_domains'] = array('allowed.example');
        Functions\when('get_site_option')->alias(
            static fn(string $name) => 'limited_email_domains' === $name
                ? array('limited.example')
                : false
        );
        Functions\when('add_site_option')->justReturn(true);
        Functions\when('is_email_address_unsafe')->justReturn(true);
        $username = str_repeat('a', 61) . '1';

        $result = UserSignupValidator::validate('unknown-idp', $username, 'user@blocked.example');

        self::assertContains('user_idp', $result['errors']->get_error_codes());
        self::assertGreaterThanOrEqual(2, count($result['errors']->get_error_messages('user_name')));
        self::assertGreaterThanOrEqual(3, count($result['errors']->get_error_messages('user_email')));
    }

    public function testValidateRejectsIllegalNameAndDuplicateAccountData(): void
    {
        Functions\when('username_exists')->justReturn(7);
        Functions\when('email_exists')->justReturn(7);

        $result = UserSignupValidator::validate('idp-one', 'admin', 'admin@example.org');

        self::assertContains('user_name', $result['errors']->get_error_codes());
        self::assertContains('user_email', $result['errors']->get_error_codes());
        self::assertStringContainsString(
            'not allowed',
            implode(' ', $result['errors']->get_error_messages('user_name'))
        );
    }
}

class UserSignupValidatorWpdb
{
    /** @var string */
    public $signups = 'wp_signups';

    /** @var array<int, string> */
    public $preparedArguments = array();

    /** @var int */
    public $queryCount = 0;

    public function prepare(string $query, string $username, string $email): string
    {
        $this->preparedArguments = array($username, $email);
        return $query;
    }

    public function query(string $query): int
    {
        $this->queryCount++;
        return 1;
    }
}
