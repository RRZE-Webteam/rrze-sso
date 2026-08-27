<?php

namespace RRZE\SSO\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base test case for isolated WordPress unit tests.
 */
abstract class TestCase extends PhpUnitTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Initializes Brain Monkey before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    /**
     * Restores patched WordPress functions after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
