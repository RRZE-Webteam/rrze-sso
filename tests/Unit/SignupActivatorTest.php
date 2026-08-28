<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\SignupActivator;
use RRZE\SSO\Tests\TestCase;
use WP_Error;

#[CoversClass(SignupActivator::class)]
class SignupActivatorTest extends TestCase
{
    /** @var object|null */
    private $previousWpdb;

    /** @var SignupActivatorWpdb */
    private $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $this->wpdb = new SignupActivatorWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        Functions\when('maybe_unserialize')->returnArg();
        Functions\when('wp_generate_password')->justReturn('temporary-pass');
        Functions\when('current_time')->justReturn('2026-08-28 10:00:00');
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

    public function testActivateRejectsInvalidOrMissingSignupKeys(): void
    {
        self::assertFalse(SignupActivator::activate(array('invalid')));
        self::assertFalse(SignupActivator::activate(''));
        self::assertFalse(SignupActivator::activate('missing'));
    }

    public function testActivateCreatesUserAndCompletesPendingSignup(): void
    {
        $this->wpdb->row = (object) array(
            'active' => 0,
            'meta' => array('locale' => 'de_DE'),
            'user_login' => 'alice@example.org',
            'user_email' => 'alice@example.org',
            'domain' => '',
        );

        Functions\when('username_exists')->justReturn(false);
        Functions\expect('wpmu_create_user')
            ->once()
            ->with('alice@example.org', 'temporary-pass', 'alice@example.org')
            ->andReturn(17);
        Functions\expect('wpmu_welcome_user_notification')
            ->once()
            ->with(17, 'temporary-pass', array('locale' => 'de_DE'));

        self::assertTrue(SignupActivator::activate('activation-key'));
        self::assertSame(
            array(
                'table' => 'wp_signups',
                'data' => array('active' => 1, 'activated' => '2026-08-28 10:00:00'),
                'where' => array('activation_key' => 'activation-key'),
            ),
            $this->wpdb->lastUpdate
        );
    }

    public function testExistingUserSignupIsMarkedActiveWithoutWelcomeMessage(): void
    {
        $this->wpdb->row = (object) array(
            'active' => 0,
            'meta' => array(),
            'user_login' => 'existing@example.org',
            'user_email' => 'existing@example.org',
            'domain' => '',
        );

        Functions\when('username_exists')->justReturn(23);
        Functions\expect('wpmu_create_user')->never();
        Functions\expect('wpmu_welcome_user_notification')->never();

        self::assertFalse(SignupActivator::activate('existing-key'));
        self::assertSame(array('activation_key' => 'existing-key'), $this->wpdb->lastUpdate['where']);
    }

    public function testActivateReturnsFalseWhenUserCannotBeCreated(): void
    {
        $this->wpdb->row = $this->siteSignup();
        Functions\when('username_exists')->justReturn(false);
        Functions\when('wpmu_create_user')->justReturn(false);

        self::assertFalse(SignupActivator::activate('failed-user-key'));
        self::assertSame(array(), $this->wpdb->lastUpdate);
    }

    public function testActivateCreatesSiteAndSendsWelcomeNotification(): void
    {
        $this->wpdb->row = $this->siteSignup();
        Functions\when('username_exists')->justReturn(29);
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\expect('wpmu_create_blog')
            ->once()
            ->with(
                'site.example.org',
                '/research/',
                'Research Site',
                29,
                array('lang_id' => 1),
                1
            )
            ->andReturn(44);
        Functions\expect('wpmu_welcome_notification')
            ->once()
            ->with(44, 29, 'temporary-pass', 'Research Site', array('lang_id' => 1));

        self::assertTrue(SignupActivator::activate('site-key'));
        self::assertSame(array('activation_key' => 'site-key'), $this->wpdb->lastUpdate['where']);
    }

    public function testTakenSiteIsMarkedActiveWithoutWelcomeNotification(): void
    {
        $this->wpdb->row = $this->siteSignup();
        Functions\when('username_exists')->justReturn(29);
        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\when('wpmu_create_blog')->justReturn(new WP_Error('blog_taken', 'Already exists'));
        Functions\expect('wpmu_welcome_notification')->never();

        self::assertFalse(SignupActivator::activate('taken-key'));
        self::assertSame(array('activation_key' => 'taken-key'), $this->wpdb->lastUpdate['where']);
    }

    private function siteSignup(): object
    {
        return (object) array(
            'active' => 0,
            'meta' => array('lang_id' => 1),
            'user_login' => 'owner@example.org',
            'user_email' => 'owner@example.org',
            'domain' => 'site.example.org',
            'path' => '/research/',
            'title' => 'Research Site',
        );
    }
}

class SignupActivatorWpdb
{
    /** @var string */
    public $signups = 'wp_signups';

    /** @var int */
    public $siteid = 1;

    /** @var object|null */
    public $row;

    /** @var array<string, mixed> */
    public $lastUpdate = array();

    public function prepare(string $query, ...$arguments): string
    {
        return $query . '|' . implode('|', $arguments);
    }

    public function get_row(string $query): ?object
    {
        return $this->row;
    }

    public function update(string $table, array $data, array $where): int
    {
        $this->lastUpdate = compact('table', 'data', 'where');
        return 1;
    }
}
