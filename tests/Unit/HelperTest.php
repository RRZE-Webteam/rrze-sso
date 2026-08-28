<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Helper;
use RRZE\SSO\SimpleSAML as SimpleSamlService;
use RRZE\SSO\Tests\TestCase;
use SimpleSAML\Auth\Simple as AuthClient;
use SimpleSAML\Session;
use WP_Error;

#[CoversClass(Helper::class)]
class HelperTest extends TestCase
{
    /** @var array<string, mixed> */
    private $options = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->options = array(
            'force_sso' => 1,
            'domain_scope' => array('idp-key' => 'example.org'),
        );
        Session::reset();
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->alias(fn(): array => $this->options);
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('sanitize_title')->alias(static fn(string $value): string => strtolower($value));
    }

    public function testGetCurrentUserSamlAttributesNormalizesKnownValuesAndLogin(): void
    {
        $client = new HelperAuthClient(
            true,
            'idp-key',
            array(
                'urn:oid:uid' => array('alice'),
                'mail' => array('alice@example.org'),
                'eduPersonAffiliation' => array('member', 'staff'),
            )
        );
        $service = Mockery::mock(SimpleSamlService::class);
        $service->shouldReceive('getAuthSimple')->once()->andReturn($client);
        $service->shouldReceive('getIdentityProviders')->once()->andReturn(array('idp-key' => 'Example IdP'));
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);

        $attributes = Helper::getCurrentUserSamlAtts();

        self::assertSame('alice', $attributes['uid']);
        self::assertSame('alice@example.org', $attributes['mail']);
        self::assertSame(array('member', 'staff'), $attributes['eduPersonAffiliation']);
        self::assertSame('alice@example.org', $attributes['wp_user_login']);
    }

    public function testGetCurrentUserSamlAttributesReportsDisabledSso(): void
    {
        $this->options['force_sso'] = 0;

        $result = Helper::getCurrentUserSamlAtts();

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('sso_is_not_activated', $result->get_error_code());
    }

    public function testGetCurrentUserSamlAttributesReportsMissingClient(): void
    {
        $service = Mockery::mock(SimpleSamlService::class);
        $service->shouldReceive('getAuthSimple')->once()->andReturn(null);
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);

        $result = Helper::getCurrentUserSamlAtts();

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('unable_to_instantiate_simplesaml_auth', $result->get_error_code());
    }

    public function testGetCurrentUserSamlAttributesReportsUnauthenticatedSession(): void
    {
        $client = new HelperAuthClient(false, 'idp-key', array());
        $service = Mockery::mock(SimpleSamlService::class);
        $service->shouldReceive('getAuthSimple')->once()->andReturn($client);
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);

        $result = Helper::getCurrentUserSamlAtts();

        self::assertSame('user_not_authenticated', $result->get_error_code());
        self::assertSame(1, Session::getSessionFromRequest()->cleanupCalls);
    }

    public function testGetCurrentUserSamlAttributesReportsEmptyAttributes(): void
    {
        $client = new HelperAuthClient(true, 'idp-key', array());
        $service = Mockery::mock(SimpleSamlService::class);
        $service->shouldReceive('getAuthSimple')->once()->andReturn($client);
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($service);

        $result = Helper::getCurrentUserSamlAtts();

        self::assertSame('unable_to_retrieve_user_attributes', $result->get_error_code());
    }
}

class HelperAuthClient extends AuthClient
{
    /** @var bool */
    private $authenticated;

    /** @var string */
    private $identityProvider;

    /** @var array<string, mixed> */
    private $attributes;

    public function __construct(bool $authenticated, string $identityProvider, array $attributes)
    {
        $this->authenticated = $authenticated;
        $this->identityProvider = $identityProvider;
        $this->attributes = $attributes;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function getAuthData(string $key)
    {
        return $this->identityProvider;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
