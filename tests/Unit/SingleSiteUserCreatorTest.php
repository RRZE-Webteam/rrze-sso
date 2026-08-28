<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\SingleSiteUserCreator;
use RRZE\SSO\Tests\TestCase;
use RuntimeException;
use WP_Error;

#[CoversClass(SingleSiteUserCreator::class)]
class SingleSiteUserCreatorTest extends TestCase
{
    /** @var array<string, mixed> */
    private $previousPost = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousPost = $_POST;
        $_POST = array();

        Functions\when('__')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('wp_strip_all_tags')->alias(static fn(string $value): string => strip_tags($value));
        Functions\when('remove_accents')->returnArg();
        Functions\when('sanitize_text_field')->alias(static fn(string $value): string => trim(strip_tags($value)));
        Functions\when('wp_get_user_contact_methods')->justReturn(array());
        Functions\when('validate_username')->alias(
            static fn(string $username): bool => 1 === preg_match('/^[a-z0-9.\-@]+$/i', $username)
        );
        Functions\when('username_exists')->justReturn(false);
        Functions\when('email_exists')->justReturn(false);
        Functions\when('is_email')->alias(
            static fn(string $email): bool => false !== filter_var($email, FILTER_VALIDATE_EMAIL)
        );
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->justReturn(array('domain_scope' => array('idp' => 'example.org')));
        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
    }

    protected function tearDown(): void
    {
        $_POST = $this->previousPost;
        parent::tearDown();
    }

    public function testCreateBuildsScopedUserAndInsertsIt(): void
    {
        $_POST = array(
            'user_idp' => 'idp',
            'user_login' => 'alice',
            'email' => ' alice@example.org ',
            'use_ssl' => '1',
        );

        Functions\expect('wp_insert_user')
            ->once()
            ->withArgs(
                static fn(object $user): bool => 'alice@example.org' === $user->user_login
                    && 'alice@example.org' === $user->user_email
                    && 1 === $user->use_ssl
            )
            ->andReturn(42);

        self::assertSame(42, SingleSiteUserCreator::create());
    }

    public function testCreateReturnsAllRequiredFieldErrors(): void
    {
        $result = SingleSiteUserCreator::create();

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertContains('user_login', $result->get_error_codes());
        self::assertContains('empty_email', $result->get_error_codes());
    }

    public function testCreateAssignsEditableRoleAndContactMethods(): void
    {
        $previousRoles = $GLOBALS['wp_roles'] ?? null;
        $role = new SingleSiteRole(true);
        $GLOBALS['wp_roles'] = (object) array('role_objects' => array('editor' => $role));
        $_POST = array(
            'user_idp' => 'idp',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
            'role' => 'editor',
            'orcid' => '0000-0001',
        );
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_key')->alias(static fn(string $value): string => strtolower($value));
        Functions\when('get_editable_roles')->justReturn(array('editor' => array('name' => 'Editor')));
        Functions\when('wp_get_user_contact_methods')->justReturn(array('orcid' => 'ORCID'));
        Functions\expect('wp_insert_user')
            ->once()
            ->withArgs(
                static fn(object $user): bool => 'editor' === $user->role
                    && '0000-0001' === $user->orcid
            )
            ->andReturn(51);

        self::assertSame(51, SingleSiteUserCreator::create());
        if (null === $previousRoles) {
            unset($GLOBALS['wp_roles']);
        } else {
            $GLOBALS['wp_roles'] = $previousRoles;
        }
    }

    public function testCreateCollectsInvalidAndDuplicateUserErrors(): void
    {
        $_POST = array(
            'user_idp' => 'idp',
            'user_login' => 'bad user',
            'email' => 'invalid-email',
        );
        Functions\when('validate_username')->justReturn(false);
        Functions\when('username_exists')->justReturn(7);
        Functions\when('is_email')->justReturn(false);

        $result = SingleSiteUserCreator::create();

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertContains('user_login', $result->get_error_codes());
        self::assertContains('invalid_email', $result->get_error_codes());
    }

    public function testCreateRejectsDuplicateEmailAddress(): void
    {
        $_POST = array(
            'user_idp' => 'idp',
            'user_login' => 'alice',
            'email' => 'alice@example.org',
        );
        Functions\when('email_exists')->justReturn(8);

        $result = SingleSiteUserCreator::create();

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertContains('email_exists', $result->get_error_codes());
    }

    public function testCreateRejectsRoleOutsideEditableRoles(): void
    {
        $previousRoles = $GLOBALS['wp_roles'] ?? null;
        $GLOBALS['wp_roles'] = (object) array('role_objects' => array());
        $_POST = array('role' => 'administrator');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_key')->returnArg();
        Functions\when('get_editable_roles')->justReturn(array());
        Functions\when('wp_die')->alias(
            static function (string $message): void {
                throw new SingleSiteCreationTerminated($message);
            }
        );

        try {
            SingleSiteUserCreator::create();
            self::fail('Invalid role did not stop user creation.');
        } catch (SingleSiteCreationTerminated $exception) {
            self::assertStringContainsString('not allowed', $exception->getMessage());
        } finally {
            if (null === $previousRoles) {
                unset($GLOBALS['wp_roles']);
            } else {
                $GLOBALS['wp_roles'] = $previousRoles;
            }
        }
    }
}

class SingleSiteRole
{
    /** @var bool */
    private $canEditUsers;

    public function __construct(bool $canEditUsers)
    {
        $this->canEditUsers = $canEditUsers;
    }

    public function has_cap(string $capability): bool
    {
        return 'edit_users' === $capability && $this->canEditUsers;
    }
}

class SingleSiteCreationTerminated extends RuntimeException
{
}
