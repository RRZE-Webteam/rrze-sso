<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Authenticate;
use RRZE\SSO\Plugin;
use RRZE\SSO\SimpleSAML as SimpleSamlService;
use RRZE\SSO\Tests\TestCase;
use RuntimeException;
use SimpleSAML\Auth\Simple as AuthClient;
use SimpleSAML\Session;
use WP_Error;
use WP_User;

/**
 * Tests the WordPress-facing authentication workflow in isolation.
 */
#[CoversClass(Authenticate::class)]
class AuthenticateTest extends TestCase
{
    /**
     * Plugin options returned to the authenticator.
     *
     * @var array<string, mixed>
     */
    private $options = array();

    /**
     * User metadata written during a test.
     *
     * @var array<int, array<string, mixed>>
     */
    private $updatedMeta = array();

    /**
     * WordPress user rows updated during a test.
     *
     * @var array<int, array<string, mixed>>
     */
    private $updatedUsers = array();

    /**
     * Provides the WordPress behavior shared by the authentication tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->options = array(
            'domain_scope' => array('idp-key' => 'example.org'),
        );
        $this->updatedMeta = array();
        $this->updatedUsers = array();
        Session::reset();

        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->alias(
            function (string $name) {
                if ('rrze_sso' === $name) {
                    return $this->options;
                }

                return false;
            }
        );
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('__')->returnArg();
        Functions\when('sanitize_title')->alias(
            static fn(string $value): string => strtolower(trim($value))
        );
        Functions\when('sanitize_text_field')->alias(
            static fn(string $value): string => trim(strip_tags($value))
        );
        Functions\when('wp_strip_all_tags')->alias(
            static fn(string $value): string => strip_tags($value)
        );
        Functions\when('remove_accents')->returnArg();
        Functions\when('is_email')->alias(
            static fn(string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false
        );
        Functions\when('is_wp_error')->alias(
            static fn($value): bool => $value instanceof WP_Error
        );
        Functions\when('update_user_meta')->alias(
            function (int $userId, string $key, $value): bool {
                $this->updatedMeta[$userId][$key] = $value;

                return true;
            }
        );
        Functions\when('wp_update_user')->alias(
            function (array $data): int {
                $this->updatedUsers[] = $data;

                return (int) $data['ID'];
            }
        );
    }

    /**
     * Ensures an authentication result produced earlier in the filter chain is preserved.
     *
     * @return void
     */
    public function testAuthenticateReturnsAnExistingAuthenticationResult(): void
    {
        $client = Mockery::mock(AuthClient::class);
        $client->shouldNotReceive('requireAuth');
        $authenticator = new Authenticate($client);
        $authenticatedUser = new WP_User(7);

        $result = $authenticator->authenticate($authenticatedUser, 'ignored');

        self::assertSame($authenticatedUser, $result);
        self::assertSame(0, Session::getSessionFromRequest()->cleanupCalls);
    }

    /**
     * Ensures SAML values synchronize an existing WordPress account.
     *
     * @return void
     */
    public function testAuthenticateSynchronizesAnExistingUser(): void
    {
        $rawAttributes = array(
            'urn:oid:uid' => array('alice'),
            'mail' => array('ALICE@example.org'),
            'displayName' => array('Alice Example'),
            'givenName' => array('Alice'),
            'sn' => array('Example'),
            'o' => array('Example University'),
            'eduPersonAffiliation' => array('member', 'staff'),
            'eduPersonScopedAffiliation' => array('staff@example.org'),
            'eduPersonEntitlement' => array('urn:example:entitlement'),
        );
        $authenticator = $this->authenticator($rawAttributes);
        $user = new WP_User(7);
        $user->display_name = 'Old Name';

        Functions\expect('get_user_by')
            ->once()
            ->with('login', 'alice@example.org')
            ->andReturn($user);
        Functions\when('get_user_meta')->alias(
            static fn(int $userId, string $key): string => array(
                'first_name' => 'Old First Name',
                'last_name' => 'Old Last Name',
            )[$key] ?? ''
        );

        $result = $authenticator->authenticate(null, 'submitted-login');

        self::assertSame(7, $result->ID);
        self::assertSame('Alice', $this->updatedMeta[7]['first_name']);
        self::assertSame('Example', $this->updatedMeta[7]['last_name']);
        self::assertSame('idp-key', $this->updatedMeta[7]['saml_sp_idp']);
        self::assertSame('Example University', $this->updatedMeta[7]['organization_name']);
        self::assertSame(array('member', 'staff'), $this->updatedMeta[7]['edu_person_affiliation']);
        self::assertSame($rawAttributes, $this->updatedMeta[7]['sso_attributes']);
        self::assertSame(
            array(array('ID' => 7, 'display_name' => 'Alice Example')),
            $this->updatedUsers
        );
        self::assertSame(1, Session::getSessionFromRequest()->cleanupCalls);
    }

    /**
     * Ensures registration builds a WordPress user from SAML attributes.
     *
     * @return void
     */
    public function testAuthenticateCreatesAUserWhenRegistrationIsEnabled(): void
    {
        $rawAttributes = array(
            'subject-id' => array('new.user@identity.example'),
            'displayName' => array('New User'),
            'givenName' => array('New'),
            'sn' => array('User'),
        );
        $authenticator = $this->authenticator($rawAttributes);
        $authenticator->setRegistration(true);
        $insertedUser = array();

        Functions\expect('get_user_by')
            ->once()
            ->with('login', 'new.user@example.org')
            ->andReturn(false);
        Functions\when('wp_generate_password')->justReturn('generated-password');
        Functions\when('wp_insert_user')->alias(
            function (array $data) use (&$insertedUser): int {
                $insertedUser = $data;

                return 11;
            }
        );

        $result = $authenticator->authenticate(null, '');

        self::assertSame(11, $result->ID);
        self::assertSame('generated-password', $insertedUser['user_pass']);
        self::assertSame('new.user@example.org', $insertedUser['user_login']);
        self::assertMatchesRegularExpression(
            '/^dummy\.[a-f0-9]{8}@rrze\.sso$/',
            $insertedUser['user_email']
        );
        self::assertSame('New User', $insertedUser['display_name']);
        self::assertSame('subscriber', $insertedUser['role']);
        self::assertSame('Example Identity Provider', $this->updatedMeta[11]['organization_name']);
        self::assertSame($rawAttributes, $this->updatedMeta[11]['sso_attributes']);
    }

    /**
     * Ensures an unknown user is denied when automatic registration is disabled.
     *
     * @return void
     */
    public function testAuthenticateRejectsAnUnknownUserWhenRegistrationIsDisabled(): void
    {
        $authenticator = $this->authenticator(
            array(
                'uid' => array('unknown'),
                'mail' => array('unknown@example.org'),
            )
        );

        Functions\expect('get_user_by')
            ->once()
            ->with('login', 'unknown@example.org')
            ->andReturn(false);
        $this->stubAuthenticationFailurePage();

        $this->expectException(AuthenticationTerminated::class);
        $this->expectExceptionMessage('unknown@example.org');

        $authenticator->authenticate(null, '');
    }

    /**
     * Ensures authentication stops when the identity provider returns no attributes.
     *
     * @return void
     */
    public function testAuthenticateRejectsAnEmptySamlResponse(): void
    {
        $client = Mockery::mock(AuthClient::class);
        $client->shouldReceive('requireAuth')->once();
        $client->shouldReceive('getAuthData')
            ->once()
            ->with('saml:sp:IdP')
            ->andReturn('idp-key');
        $client->shouldReceive('getAttributes')
            ->once()
            ->andReturn(array());
        $authenticator = new Authenticate($client);
        $this->stubAuthenticationFailurePage();

        $this->expectException(AuthenticationTerminated::class);
        $this->expectExceptionMessage('User attributes could not be retrieved.');

        $authenticator->authenticate(null, '');
    }

    /**
     * Ensures responses from unconfigured identity providers are rejected.
     *
     * @return void
     */
    public function testAuthenticateRejectsAnUnknownIdentityProvider(): void
    {
        $client = Mockery::mock(AuthClient::class);
        $client->shouldReceive('requireAuth')->once();
        $client->shouldReceive('getAuthData')
            ->once()
            ->with('saml:sp:IdP')
            ->andReturn('unknown-idp');
        $client->shouldReceive('getAttributes')
            ->once()
            ->andReturn(array('uid' => array('alice')));

        $plugin = Mockery::mock(Plugin::class);
        $plugin->shouldReceive('getBaseName')
            ->once()
            ->andReturn('rrze-sso/rrze-sso.php');
        $simpleSaml = Mockery::mock(SimpleSamlService::class);
        $simpleSaml->shouldReceive('getIdentityProviders')
            ->once()
            ->andReturn(array('idp-key' => 'Example Identity Provider'));
        Functions\when('RRZE\SSO\plugin')->justReturn($plugin);
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($simpleSaml);
        $this->stubAuthenticationFailurePage();

        $this->expectException(AuthenticationTerminated::class);
        $this->expectExceptionMessage('unknown-idp');

        (new Authenticate($client))->authenticate(null, '');
    }

    /**
     * Ensures a login identifier is required in the SAML response.
     *
     * @return void
     */
    public function testAuthenticateRejectsAttributesWithoutAUserLogin(): void
    {
        $authenticator = $this->authenticator(
            array('mail' => array('alice@example.org'))
        );
        $this->stubAuthenticationFailurePage();

        $this->expectException(AuthenticationTerminated::class);
        $this->expectExceptionMessage('User login could not be determined');

        $authenticator->authenticate(null, '');
    }

    /**
     * Ensures Multisite registration creates memberships for the network and current site.
     *
     * @return void
     */
    public function testAuthenticateCreatesAMultisiteUserWithDashboardAccess(): void
    {
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new AuthenticateWpdb();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            fn(string $name) => 'rrze_sso' === $name ? $this->options : false
        );
        $authenticator = $this->authenticator(
            array(
                'uid' => array('new.user'),
                'mail' => array('new.user@example.org'),
            )
        );
        $authenticator->setRegistration(true);
        $memberships = array();

        Functions\when('get_user_by')->justReturn(false);
        Functions\when('wp_generate_password')->justReturn('generated-password');
        Functions\when('wp_insert_user')->justReturn(23);
        Functions\when('switch_to_blog')->justReturn(true);
        Functions\when('restore_current_blog')->justReturn(true);
        Functions\when('get_current_blog_id')->justReturn(3);
        Functions\when('is_user_member_of_blog')->justReturn(false);
        Functions\when('add_user_to_blog')->alias(
            static function (int $blogId, int $userId, string $role) use (&$memberships): bool {
                $memberships[] = array($blogId, $userId, $role);

                return true;
            }
        );
        Functions\when('get_blogs_of_user')->justReturn(array());
        Functions\when('is_super_admin')->justReturn(true);

        try {
            $result = $authenticator->authenticate(null, '');

            self::assertSame(23, $result->ID);
            self::assertSame(
                array(
                    array(1, 23, 'subscriber'),
                    array(3, 23, 'subscriber'),
                ),
                $memberships
            );
            self::assertSame('idp-key', $this->updatedMeta[23]['saml_sp_idp']);
        } finally {
            if (null === $previousWpdb) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    }

    /**
     * Ensures the current and legacy registration filters remain supported.
     *
     * @return void
     */
    public function testLoadedRetainsBothRegistrationFilters(): void
    {
        $client = Mockery::mock(AuthClient::class);
        $authenticator = new TestableAuthenticate($client);

        Filters\expectApplied('rrze_sso_registration')
            ->once()
            ->with(false)
            ->andReturn(true);
        Filters\expectApplied('fau_websso_registration')
            ->once()
            ->with(true)
            ->andReturn(true);

        $authenticator->loaded();

        self::assertTrue($authenticator->registrationEnabled());
    }

    /**
     * Ensures disabled Multisite registration installs the signup redirect.
     *
     * @return void
     */
    public function testLoadedRedirectsSignupWhenMultisiteRegistrationIsDisabled(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            function (string $name) {
                return 'rrze_sso' === $name ? $this->options : 'none';
            }
        );
        Functions\when('apply_filters')->alias(static fn(string $hook, $value) => $value);
        Functions\expect('add_action')
            ->once()
            ->with(
                'before_signup_header',
                Mockery::on(
                    static fn(array $callback): bool => 'redirectToSiteUrl' === $callback[1]
                )
            );
        $authenticator = new TestableAuthenticate(Mockery::mock(AuthClient::class));

        $authenticator->loaded();

        self::assertFalse($authenticator->registrationEnabled());
    }

    /**
     * Ensures enabled Multisite registration does not install the signup redirect.
     *
     * @return void
     */
    public function testLoadedAllowsSignupWhenMultisiteRegistrationIsEnabled(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            function (string $name) {
                return 'rrze_sso' === $name ? $this->options : 'all';
            }
        );
        Functions\when('apply_filters')->alias(static fn(string $hook, $value) => $value);
        Functions\expect('add_action')
            ->never()
            ->with(
                'before_signup_header',
                Mockery::type('array')
            );
        $authenticator = new TestableAuthenticate(Mockery::mock(AuthClient::class));

        $authenticator->loaded();

        self::assertTrue($authenticator->registrationEnabled());
    }

    /**
     * Ensures malformed identity-provider data cannot be treated as an entity ID.
     *
     * @return void
     */
    public function testAuthenticateRejectsANonScalarIdentityProviderId(): void
    {
        $authenticator = $this->authenticator(
            array('uid' => array('alice')),
            array('unexpected-idp')
        );
        $this->stubAuthenticationFailurePage();

        $this->expectException(AuthenticationTerminated::class);
        $this->expectExceptionMessage('is not registered on this SP');

        $authenticator->authenticate(null, '');
    }

    /**
     * Ensures malformed optional provider settings do not alter a valid login.
     *
     * @return void
     */
    public function testAuthenticateIgnoresNonScalarProviderNameAndDomainScope(): void
    {
        $this->options['domain_scope']['idp-key'] = array('invalid');
        $authenticator = $this->authenticator(
            array('uid' => array('alice')),
            'idp-key',
            array('idp-key' => array('invalid'))
        );
        $user = new WP_User(7);

        Functions\expect('get_user_by')
            ->once()
            ->with('login', 'alice')
            ->andReturn($user);
        Functions\when('get_user_meta')->justReturn('');

        $result = $authenticator->authenticate(null, '');

        self::assertSame(7, $result->ID);
        self::assertSame('', $this->updatedMeta[7]['organization_name']);
    }

    /**
     * Ensures matching profile values are not needlessly rewritten.
     *
     * @return void
     */
    public function testAuthenticateLeavesUnchangedNameFieldsAlone(): void
    {
        $authenticator = $this->authenticator(
            array(
                'uid' => array('alice'),
                'displayName' => array('Alice Example'),
                'givenName' => array('Alice'),
                'sn' => array('Example'),
            )
        );
        $user = new WP_User(7);
        $user->display_name = 'Alice Example';

        Functions\when('get_user_by')->justReturn($user);
        Functions\when('get_user_meta')->alias(
            static fn(int $userId, string $key): string => array(
                'first_name' => 'Alice',
                'last_name' => 'Example',
            )[$key] ?? ''
        );

        $result = $authenticator->authenticate(null, '');

        self::assertSame(7, $result->ID);
        self::assertSame(array(), $this->updatedUsers);
        self::assertArrayNotHasKey('first_name', $this->updatedMeta[7]);
        self::assertArrayNotHasKey('last_name', $this->updatedMeta[7]);
    }

    /**
     * Ensures usernames changed by normalization are rejected.
     *
     * @return void
     */
    public function testAuthenticateRejectsAUsernameThatRequiresSanitizing(): void
    {
        $authenticator = $this->authenticator(
            array('uid' => array('<b>alice</b>'))
        );
        $this->stubAuthenticationFailurePage();

        $this->expectException(AuthenticationTerminated::class);
        $this->expectExceptionMessage('username entered is not valid');

        $authenticator->authenticate(null, '');
    }

    /**
     * Ensures an existing Multisite user is added to the primary site during registration.
     *
     * @return void
     */
    public function testAuthenticateAddsAnExistingMultisiteUserToThePrimarySite(): void
    {
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new AuthenticateWpdb();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            fn(string $name) => 'rrze_sso' === $name ? $this->options : false
        );
        $authenticator = $this->authenticator(
            array(
                'uid' => array('alice'),
                'mail' => array('alice@example.org'),
                'organizationName' => array('Array Organization'),
            )
        );
        $authenticator->setRegistration(true);
        $user = new WP_User(7);
        $memberships = array();

        Functions\when('get_user_by')->justReturn($user);
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('is_user_member_of_blog')->justReturn(false);
        Functions\when('add_user_to_blog')->alias(
            static function (int $blogId, int $userId, string $role) use (&$memberships): bool {
                $memberships[] = array($blogId, $userId, $role);

                return true;
            }
        );
        Functions\when('get_blogs_of_user')->justReturn(array());
        Functions\when('is_super_admin')->justReturn(true);

        try {
            $result = $authenticator->authenticate(null, '');

            self::assertSame(7, $result->ID);
            self::assertSame(array(array(1, 7, 'subscriber')), $memberships);
            self::assertSame('Array Organization', $this->updatedMeta[7]['organization_name']);
        } finally {
            if (null === $previousWpdb) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    }

    /**
     * Ensures a failed Multisite insertion restores the original site before reporting the error.
     *
     * @return void
     */
    public function testAuthenticateRestoresTheSiteAfterMultisiteUserCreationFails(): void
    {
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new AuthenticateWpdb();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            fn(string $name) => 'rrze_sso' === $name ? $this->options : false
        );
        $authenticator = $this->authenticator(
            array('uid' => array('new.user'))
        );
        $authenticator->setRegistration(true);
        $restored = false;

        Functions\when('get_user_by')->justReturn(false);
        Functions\when('wp_generate_password')->justReturn('generated-password');
        Functions\when('wp_insert_user')->justReturn(new WP_Error('insert_failed', 'Failed'));
        Functions\when('switch_to_blog')->justReturn(true);
        Functions\when('restore_current_blog')->alias(
            static function () use (&$restored): bool {
                $restored = true;

                return true;
            }
        );
        $this->stubAuthenticationFailurePage();

        try {
            $authenticator->authenticate(null, '');
            self::fail('Failed user creation did not terminate authentication.');
        } catch (AuthenticationTerminated $exception) {
            self::assertStringContainsString('user could not be added', $exception->getMessage());
            self::assertTrue($restored);
        } finally {
            if (null === $previousWpdb) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    }

    /**
     * Ensures access denial handles users without any available dashboards.
     *
     * @return void
     */
    public function testMultisiteAccessDenialHandlesAnEmptySiteList(): void
    {
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new AuthenticateWpdb();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            fn(string $name) => 'rrze_sso' === $name ? $this->options : false
        );
        $authenticator = $this->authenticator(
            array('uid' => array('alice'))
        );
        $user = new WP_User(7);

        Functions\when('get_user_by')->justReturn($user);
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('get_blogs_of_user')->justReturn(array());
        Functions\when('is_super_admin')->justReturn(false);
        Functions\when('get_current_blog_id')->justReturn(3);
        Functions\when('wp_list_filter')->justReturn(array());
        $this->stubAuthenticationFailurePage();

        try {
            $authenticator->authenticate(null, '');
            self::fail('Dashboard access denial did not terminate authentication.');
        } catch (AuthenticationTerminated $exception) {
            self::assertStringNotContainsString('Your Websites', $exception->getMessage());
        } finally {
            if (null === $previousWpdb) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    }

    /**
     * Ensures login redirects are retained by the generated SSO login URL.
     *
     * @return void
     */
    public function testLoginUrlRetainsTheRedirectTarget(): void
    {
        $authenticator = new Authenticate(Mockery::mock(AuthClient::class));
        $redirect = 'https://example.org/private/?page=1';

        Functions\expect('site_url')
            ->once()
            ->with('wp-login.php', 'login')
            ->andReturn('https://example.org/wp-login.php');
        Functions\expect('add_query_arg')
            ->once()
            ->with(
                'redirect_to',
                urlencode($redirect),
                'https://example.org/wp-login.php'
            )
            ->andReturn('https://example.org/wp-login.php?redirect_to=encoded');

        self::assertSame(
            'https://example.org/wp-login.php?redirect_to=encoded',
            $authenticator->loginUrl('https://old.example/login', $redirect)
        );
    }

    /**
     * Ensures an empty redirect target produces the plain SSO login URL.
     *
     * @return void
     */
    public function testLoginUrlWithoutRedirectUsesThePlainLoginUrl(): void
    {
        $authenticator = new Authenticate(Mockery::mock(AuthClient::class));
        Functions\expect('site_url')
            ->once()
            ->with('wp-login.php', 'login')
            ->andReturn('https://example.org/wp-login.php');
        Functions\expect('add_query_arg')->never();

        self::assertSame(
            'https://example.org/wp-login.php',
            $authenticator->loginUrl('https://old.example/login', '')
        );
    }

    /**
     * Ensures WordPress logout also terminates and cleans the SAML session.
     *
     * @return void
     */
    public function testLogoutTerminatesTheSamlSession(): void
    {
        $client = Mockery::mock(AuthClient::class);
        $client->shouldReceive('logout')
            ->once()
            ->with('https://example.org');
        $authenticator = new Authenticate($client);

        Functions\expect('site_url')
            ->once()
            ->with('', 'https')
            ->andReturn('https://example.org');

        $authenticator->logout(7);

        self::assertSame(1, Session::getSessionFromRequest()->cleanupCalls);
    }

    /**
     * Ensures disabled registration sends signup requests back to the site.
     *
     * @return void
     */
    public function testRedirectToSiteUrlUsesTheHttpsHomePage(): void
    {
        $authenticator = new Authenticate(Mockery::mock(AuthClient::class));

        Functions\expect('site_url')
            ->once()
            ->with('', 'https')
            ->andReturn('https://example.org');
        Functions\when('wp_redirect')->alias(
            static function (string $url): void {
                throw new AuthenticationTerminated($url);
            }
        );

        $this->expectException(AuthenticationTerminated::class);
        $this->expectExceptionMessage('https://example.org');

        $authenticator->redirectToSiteUrl();
    }

    public function testMultisiteUserWithoutCurrentDashboardAccessGetsHelpfulLinks(): void
    {
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new AuthenticateWpdb();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            fn(string $name) => 'rrze_sso' === $name ? $this->options : false
        );
        $authenticator = $this->authenticator(array(
            'uid' => array('alice'),
            'mail' => array('alice@example.org'),
            'displayName' => array('Alice Example'),
        ));
        $user = new WP_User(7);
        $user->display_name = 'Alice Example';
        $blogs = array(
            (object) array(
                'userblog_id' => 2,
                'blogname' => 'Alice Research',
            ),
        );
        $administrator = (object) array(
            'display_name' => 'Site Admin',
            'user_email' => 'admin@example.org',
        );

        Functions\when('get_user_by')->justReturn($user);
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('get_blogs_of_user')->justReturn($blogs);
        Functions\when('is_super_admin')->justReturn(false);
        Functions\when('get_current_blog_id')->justReturn(3);
        Functions\when('wp_list_filter')->justReturn(array());
        Functions\when('get_bloginfo')->justReturn('Restricted Site');
        Functions\when('get_admin_url')->alias(static fn(int $id): string => "https://example.org/site-{$id}/wp-admin/");
        Functions\when('get_home_url')->alias(static fn(int $id): string => "https://example.org/site-{$id}/");
        Functions\when('esc_url')->returnArg();
        Functions\when('get_users')->justReturn(array($administrator));
        Functions\when('make_clickable')->alias(
            static fn(string $email): string => '<a href="mailto:' . $email . '">' . $email . '</a>'
        );
        Functions\when('wp_logout_url')->justReturn('https://example.org/logout');
        Functions\when('wp_die')->alias(
            static function ($message): void {
                throw new AuthenticationTerminated((string) $message);
            }
        );

        try {
            $authenticator->authenticate(null, '');
            self::fail('Dashboard access denial did not terminate authentication.');
        } catch (AuthenticationTerminated $exception) {
            self::assertStringContainsString('Alice Research', $exception->getMessage());
            self::assertStringContainsString('Site Admin', $exception->getMessage());
            self::assertStringContainsString('admin@example.org', $exception->getMessage());
            self::assertStringContainsString('Visit the Dashboard', $exception->getMessage());
        } finally {
            if (null === $previousWpdb) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    }

    /**
     * Ensures ordinary Multisite users retain access to a dashboard they belong to.
     *
     * @return void
     */
    public function testMultisiteUserCanAccessTheCurrentDashboard(): void
    {
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new AuthenticateWpdb();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            fn(string $name) => 'rrze_sso' === $name ? $this->options : false
        );
        $authenticator = $this->authenticator(array('uid' => array('alice')));
        $user = new WP_User(7);
        $blogs = array((object) array('userblog_id' => 3));

        Functions\when('get_user_by')->justReturn($user);
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('get_blogs_of_user')->justReturn($blogs);
        Functions\when('is_super_admin')->justReturn(false);
        Functions\when('get_current_blog_id')->justReturn(3);
        Functions\when('wp_list_filter')->justReturn($blogs);
        Functions\expect('wp_die')->never();

        try {
            $result = $authenticator->authenticate(null, '');

            self::assertSame(7, $result->ID);
            self::assertSame(
                array('uid' => array('alice')),
                $this->updatedMeta[7]['sso_attributes']
            );
        } finally {
            if (null === $previousWpdb) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
        }
    }

    /**
     * Creates an authenticator backed by a successful SAML response.
     *
     * @param array<string, mixed> $attributes         SAML attributes to return.
     * @param mixed                $identityProviderId Identity-provider data to return.
     * @param array<string, mixed> $providers          Configured identity providers.
     * @return TestableAuthenticate Configured authenticator.
     */
    private function authenticator(
        array $attributes,
        $identityProviderId = 'idp-key',
        array $providers = array('idp-key' => 'Example Identity Provider')
    ): TestableAuthenticate {
        $client = Mockery::mock(AuthClient::class);
        $client->shouldReceive('requireAuth')->once();
        $client->shouldReceive('getAuthData')
            ->once()
            ->with('saml:sp:IdP')
            ->andReturn($identityProviderId);
        $client->shouldReceive('getAttributes')
            ->once()
            ->andReturn($attributes);

        $plugin = Mockery::mock(Plugin::class);
        $plugin->shouldReceive('getBaseName')
            ->once()
            ->andReturn('rrze-sso/rrze-sso.php');
        $simpleSaml = Mockery::mock(SimpleSamlService::class);
        $simpleSaml->shouldReceive('getIdentityProviders')
            ->once()
            ->andReturn($providers);

        Functions\when('RRZE\SSO\plugin')->justReturn($plugin);
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($simpleSaml);

        return new TestableAuthenticate($client);
    }

    /**
     * Makes the authentication error page observable without stopping PHPUnit.
     *
     * @return void
     */
    private function stubAuthenticationFailurePage(): void
    {
        Functions\when('esc_html')->returnArg();
        Functions\when('get_bloginfo')->justReturn('Example Website');
        Functions\when('get_users')->justReturn(array());
        Functions\when('wp_logout_url')->justReturn('https://example.org/logout');
        Functions\when('wp_die')->alias(
            static function ($message): void {
                throw new AuthenticationTerminated((string) $message);
            }
        );
    }
}

/**
 * Exposes mutable registration state without changing the production API.
 */
class TestableAuthenticate extends Authenticate
{
    /**
     * Enables or disables user registration for a test.
     *
     * @param bool $registration Whether registration is enabled.
     * @return void
     */
    public function setRegistration(bool $registration): void
    {
        $this->registration = $registration;
    }

    /**
     * Reports registration state after hook registration.
     *
     * @return bool Whether registration is enabled.
     */
    public function registrationEnabled(): bool
    {
        return $this->registration;
    }
}

/**
 * Exception used to replace WordPress's terminating wp_die() call.
 */
class AuthenticationTerminated extends RuntimeException
{
}

class AuthenticateWpdb
{
    /** @var string */
    public $signups = 'wp_signups';

    public function prepare(string $query, ...$arguments): string
    {
        return $query . '|' . implode('|', $arguments);
    }

    public function get_var(string $query)
    {
        return null;
    }
}
