<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RRZE\SSO\SimpleSAML;
use RRZE\SSO\Tests\TestCase;
use RRZE\SSO\UserRegistrationHandler;
use RuntimeException;
use WP_Error;

#[CoversClass(UserRegistrationHandler::class)]
class UserRegistrationHandlerTest extends TestCase
{
    /** @var array<string, mixed> */
    private $previousRequest = array();

    /** @var array<string, mixed> */
    private $previousPost = array();

    /** @var object|null */
    private $previousWpdb;

    /** @var UserRegistrationHandlerWpdb */
    private $wpdb;

    /** @var bool */
    private $multisite = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousRequest = $_REQUEST;
        $this->previousPost = $_POST;
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $_REQUEST = array();
        $_POST = array();
        $this->wpdb = new UserRegistrationHandlerWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_key')->alias(
            static fn(string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        Functions\when('__')->returnArg();
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('check_admin_referer')->justReturn(true);
        Functions\when('is_multisite')->alias(fn(): bool => $this->multisite);
        Functions\when('get_option')->alias(
            static fn(string $name) => match ($name) {
                'rrze_sso' => array('domain_scope' => array('idp-one' => 'example.org')),
                'blogname' => 'Example Site',
                'default_role' => 'subscriber',
                default => false,
            }
        );
        Functions\when('get_site_option')->alias(
            static fn(string $name) => match ($name) {
                'rrze_sso' => array('domain_scope' => array('idp-one' => 'example.org')),
                'illegal_names' => array('admin', 'root'),
                'limited_email_domains' => array(),
                default => false,
            }
        );
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
        Functions\when('sanitize_title')->alias(static fn(string $value): string => strtolower(trim($value)));
        Functions\when('wp_strip_all_tags')->alias(static fn(string $value): string => strip_tags($value));
        Functions\when('remove_accents')->returnArg();
        Functions\when('sanitize_email')->alias(static fn(string $value): string => strtolower(trim($value)));
        Functions\when('sanitize_text_field')->alias(static fn(string $value): string => trim(strip_tags($value)));
        Functions\when('is_email_address_unsafe')->justReturn(false);
        Functions\when('is_email')->alias(
            static fn(string $email): bool => false !== filter_var($email, FILTER_VALIDATE_EMAIL)
        );
        Functions\when('validate_username')->alias(
            static fn(string $username): bool => 1 === preg_match('/^[a-z0-9.\-@]+$/i', $username)
        );
        Functions\when('username_exists')->justReturn(false);
        Functions\when('email_exists')->justReturn(false);
        Functions\when('apply_filters')->alias(static fn(string $hook, $value) => $value);
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\when('wp_generate_password')->justReturn('temporary-pass');
        Functions\when('wp_get_user_contact_methods')->justReturn(array());
        Functions\when('wp_insert_user')->justReturn(42);
        Functions\when('wpmu_create_user')->justReturn(17);
        Functions\when('get_userdata')->alias(
            static fn(int $userId): object => (object) array(
                'ID' => $userId,
                'user_login' => 'alice@example.org',
                'user_email' => 'alice@example.org',
            )
        );
        Functions\when('wp_set_password')->justReturn(null);
        Functions\when('wp_specialchars_decode')->alias(static fn(string $value): string => html_entity_decode($value));
        Functions\when('wp_login_url')->justReturn('https://example.org/wp-login.php');
        Functions\when('wp_mail')->justReturn(true);
        Functions\when('add_query_arg')->alias(
            static fn(array $arguments, string $url): string => $url . '?' . http_build_query($arguments)
        );
        Functions\when('wp_redirect')->alias(
            static function (string $url): void {
                throw new RegistrationRedirected($url);
            }
        );

        $simpleSaml = Mockery::mock(SimpleSAML::class);
        $simpleSaml->shouldReceive('getIdentityProviders')
            ->zeroOrMoreTimes()
            ->andReturn(array('idp-one' => 'Identity Provider One'));
        Functions\when('RRZE\SSO\simpleSAML')->justReturn($simpleSaml);
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->previousRequest;
        $_POST = $this->previousPost;
        if (null === $this->previousWpdb) {
            unset($GLOBALS['wpdb']);
        } else {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        }
        parent::tearDown();
    }

    public function testHandleIgnoresUnsupportedActions(): void
    {
        $_REQUEST['action'] = array('invalid');
        Functions\expect('wp_redirect')->never();

        UserRegistrationHandler::handle();

        self::assertTrue(true);
    }

    public function testExistingUserActionRedirectsWhenAccountCannotBeFound(): void
    {
        $_REQUEST = array(
            'action' => '_admin_add-user',
            'email' => 'missing@example.org',
        );
        Functions\expect('get_user_by')->twice()->andReturn(false);

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=does_not_exist');

        UserRegistrationHandler::handle();
    }

    public function testExistingUserActionFallsBackToEmailLookup(): void
    {
        $_REQUEST = array(
            'action' => '_admin_add-user',
            'email' => 'existing@example.org',
        );
        $user = (object) array(
            'ID' => 23,
            'user_login' => 'existing@example.org',
        );
        Functions\expect('get_user_by')
            ->once()
            ->with('login', 'existing@example.org')
            ->andReturn(false);
        Functions\expect('get_user_by')
            ->once()
            ->with('email', 'existing@example.org')
            ->andReturn($user);
        Functions\when('get_blogs_of_user')->justReturn(array(3 => (object) array()));
        Functions\when('is_super_admin')->justReturn(false);
        Functions\when('get_current_blog_id')->justReturn(3);

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=addexisting');

        UserRegistrationHandler::handle();
    }

    public function testNetworkActionCreatesUserAndSendsAccountNotification(): void
    {
        $_REQUEST['action'] = '_network_add-user';
        $_POST['user'] = array(
            'idp' => 'idp-one',
            'username' => 'alice',
            'email' => 'ALICE@example.org',
        );

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=added');

        UserRegistrationHandler::handle();
    }

    public function testExistingAccountIsAddedToCurrentSite(): void
    {
        $_REQUEST = array(
            'action' => '_admin_add-user',
            'email' => 'existing@example.org',
            'role' => 'editor',
        );
        $user = (object) array(
            'ID' => 23,
            'user_login' => 'existing@example.org',
            'user_email' => 'existing@example.org',
        );

        Functions\expect('get_user_by')->once()->with('login', 'existing@example.org')->andReturn($user);
        Functions\when('get_blogs_of_user')->justReturn(array());
        Functions\when('is_super_admin')->justReturn(false);
        Functions\when('get_current_blog_id')->justReturn(3);
        Functions\expect('add_existing_user_to_blog')
            ->once()
            ->with(array('user_id' => 23, 'role' => 'editor'));
        Functions\when('get_editable_roles')->justReturn(array('editor' => array('name' => 'Editor')));
        Functions\when('translate_user_role')->returnArg();
        Functions\when('home_url')->justReturn('https://example.org');

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=add');

        UserRegistrationHandler::handle();
    }

    public function testSingleSiteActionCreatesUserAndRedirectsToUserList(): void
    {
        $_REQUEST['action'] = '_admin_create-user';
        $_POST = array(
            'user_idp' => 'idp-one',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
        );

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?update=add&id=42');

        UserRegistrationHandler::handle();
    }

    public function testMultisiteActionActivatesSignupAndSendsInvitation(): void
    {
        $this->multisite = true;
        $_REQUEST = array(
            'action' => '_admin_create-user',
            'user_idp' => 'idp-one',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
            'role' => 'author',
        );
        $this->wpdb->activationKey = 'activation-key';

        Functions\expect('wpmu_signup_user')
            ->once()
            ->with(
                'alice@example.org',
                'alice@example.org',
                array('add_to_blog' => 3, 'new_role' => 'author')
            );
        Functions\when('wpmu_activate_signup')->justReturn(array('user_id' => 31));
        Functions\when('is_super_admin')->justReturn(false);

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=newuserconfirmation');

        UserRegistrationHandler::handle();
    }

    public function testNetworkValidationErrorsAreSerializedIntoRedirect(): void
    {
        $_REQUEST['action'] = '_network_add-user';
        $_POST['user'] = array(
            'idp' => '',
            'username' => '12',
            'email' => 'invalid-email',
        );

        try {
            UserRegistrationHandler::handle();
            self::fail('Validation errors did not redirect.');
        } catch (RegistrationRedirected $exception) {
            self::assertStringContainsString('update=addusererrors', $exception->getMessage());
            self::assertStringContainsString('&error=', $exception->getMessage());
        }
    }

    public function testNetworkCreationTreatsNestedUserValuesAsEmpty(): void
    {
        $_REQUEST['action'] = '_network_add-user';
        $_POST['user'] = array(
            'idp' => array('idp-one'),
            'username' => array('alice'),
            'email' => array('alice@example.org'),
        );

        try {
            UserRegistrationHandler::handle();
            self::fail('Nested form values did not trigger validation errors.');
        } catch (RegistrationRedirected $exception) {
            self::assertStringContainsString('update=addusererrors', $exception->getMessage());
            self::assertStringContainsString('&error=', $exception->getMessage());
        }
    }

    public function testNetworkCreationRejectsMissingUserData(): void
    {
        $_REQUEST['action'] = '_network_add-user';
        Functions\when('wp_die')->alias(
            static function (string $message): void {
                throw new RegistrationPermissionDenied($message);
            }
        );

        $this->expectException(RegistrationPermissionDenied::class);
        $this->expectExceptionMessage('empty user');

        UserRegistrationHandler::handle();
    }

    public function testNetworkCreationFailureRedirectsWithError(): void
    {
        $_REQUEST['action'] = '_network_add-user';
        $_POST['user'] = array(
            'idp' => 'idp-one',
            'username' => 'alice',
            'email' => 'alice@example.org',
        );
        Functions\when('wpmu_create_user')->justReturn(false);

        try {
            UserRegistrationHandler::handle();
            self::fail('Failed user creation did not redirect.');
        } catch (RegistrationRedirected $exception) {
            self::assertStringContainsString('page=usernew', $exception->getMessage());
            self::assertStringContainsString('error=', $exception->getMessage());
        }
    }

    public function testExistingSiteMemberIsNotAddedAgain(): void
    {
        $_REQUEST = array(
            'action' => '_admin_add-user',
            'email' => 'existing@example.org',
        );
        $user = (object) array('ID' => 23, 'user_login' => 'existing@example.org');
        Functions\when('get_user_by')->justReturn($user);
        Functions\when('get_blogs_of_user')->justReturn(array(3 => (object) array()));
        Functions\when('is_super_admin')->justReturn(false);
        Functions\when('get_current_blog_id')->justReturn(3);
        Functions\expect('add_existing_user_to_blog')->never();

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=addexisting');

        UserRegistrationHandler::handle();
    }

    public function testSuperAdminCanSkipExistingUserConfirmation(): void
    {
        $_REQUEST = array(
            'action' => '_admin_add-user',
            'email' => 'existing@example.org',
            'role' => 'editor',
        );
        $_POST['noconfirmation'] = '1';
        $user = (object) array('ID' => 23, 'user_login' => 'existing@example.org');
        Functions\when('get_user_by')->justReturn($user);
        Functions\when('get_blogs_of_user')->justReturn(array());
        Functions\when('is_super_admin')->justReturn(true);
        Functions\when('get_current_blog_id')->justReturn(3);
        Functions\when('add_existing_user_to_blog')->justReturn(true);
        Functions\expect('wp_mail')->never();

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=addnoconfirmation');

        UserRegistrationHandler::handle();
    }

    public function testSingleSiteValidationFailureRedirectsWithError(): void
    {
        $_REQUEST['action'] = '_admin_create-user';

        try {
            UserRegistrationHandler::handle();
            self::fail('Single-site validation errors did not redirect.');
        } catch (RegistrationRedirected $exception) {
            self::assertStringContainsString('page=usernew', $exception->getMessage());
            self::assertStringContainsString('error=', $exception->getMessage());
        }
    }

    public function testSingleSiteCreationReturnsToCustomPageWithoutListCapability(): void
    {
        $_REQUEST['action'] = '_admin_create-user';
        $_POST = array(
            'user_idp' => 'idp-one',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
        );
        Functions\when('current_user_can')->alias(
            static fn(string $capability): bool => 'list_users' !== $capability
        );

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=add');

        UserRegistrationHandler::handle();
    }

    public function testMultisiteActivationErrorIsSerializedIntoRedirect(): void
    {
        $this->multisite = true;
        $_REQUEST = array(
            'action' => '_admin_create-user',
            'user_idp' => 'idp-one',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
            'role' => 'author',
        );
        Functions\when('wpmu_signup_user')->justReturn(null);
        Functions\when('wpmu_activate_signup')->justReturn(new WP_Error('activation_failed', 'Failed'));

        try {
            UserRegistrationHandler::handle();
            self::fail('Activation failure did not redirect.');
        } catch (RegistrationRedirected $exception) {
            self::assertStringContainsString('error=', $exception->getMessage());
        }
    }

    public function testMultisiteValidationFailureRedirectsWithError(): void
    {
        $this->multisite = true;
        $_REQUEST['action'] = '_admin_create-user';

        try {
            UserRegistrationHandler::handle();
            self::fail('Multisite validation errors did not redirect.');
        } catch (RegistrationRedirected $exception) {
            self::assertStringContainsString('page=usernew', $exception->getMessage());
            self::assertStringContainsString('error=', $exception->getMessage());
        }
    }

    public function testSuperAdminCanSkipMultisiteSignupConfirmation(): void
    {
        $this->multisite = true;
        $_REQUEST = array(
            'action' => '_admin_create-user',
            'user_idp' => 'idp-one',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
            'role' => 'author',
        );
        $_POST['noconfirmation'] = '1';
        $this->wpdb->activationKey = 'activation-key';
        Functions\when('wpmu_signup_user')->justReturn(null);
        Functions\when('wpmu_activate_signup')->justReturn(array('user_id' => 31));
        Functions\when('is_super_admin')->justReturn(true);
        Functions\expect('wp_mail')->never();

        $this->expectException(RegistrationRedirected::class);
        $this->expectExceptionMessage('users.php?page=usernew&update=addnoconfirmation');

        UserRegistrationHandler::handle();
    }

    #[DataProvider('unauthorizedActionsProvider')]
    public function testActionsRequireTheirExpectedCapability(array $request, string $message): void
    {
        $_REQUEST = $request;
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('wp_die')->alias(
            static function (string $message): void {
                throw new RegistrationPermissionDenied($message);
            }
        );

        $this->expectException(RegistrationPermissionDenied::class);
        $this->expectExceptionMessage($message);

        UserRegistrationHandler::handle();
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function unauthorizedActionsProvider(): array
    {
        return array(
            'network creation' => array(
                array('action' => '_network_add-user'),
                'add users to this network',
            ),
            'existing user addition' => array(
                array('action' => '_admin_add-user', 'email' => 'existing@example.org'),
                'add users to this site',
            ),
            'site creation' => array(
                array('action' => '_admin_create-user'),
                'create users',
            ),
        );
    }
}

class RegistrationRedirected extends RuntimeException
{
}

class RegistrationPermissionDenied extends RuntimeException
{
}

class UserRegistrationHandlerWpdb
{
    /** @var string */
    public $signups = 'wp_signups';

    /** @var int */
    public $blogid = 3;

    /** @var string */
    public $activationKey = '';

    public function prepare(string $query, ...$arguments): string
    {
        return $query . '|' . implode('|', $arguments);
    }

    public function query(string $query): int
    {
        return 1;
    }

    public function get_var(string $query): string
    {
        return $this->activationKey;
    }
}
