<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use RRZE\SSO\Options;
use RRZE\SSO\Tests\TestCase;

#[CoversClass(Options::class)]
class OptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('wp_parse_args')->alias(
            static fn($args, $defaults): array => array_merge((array) $defaults, (array) $args)
        );
    }

    public function testGetOptionsMergesDefaultsAndDropsUnknownValues(): void
    {
        Functions\when('is_multisite')->justReturn(false);
        Functions\expect('get_option')
            ->once()
            ->with('rrze_sso')
            ->andReturn(array('force_sso' => 1, 'unknown' => 'discarded'));

        $options = Options::getOptions();

        self::assertSame(1, $options->force_sso);
        self::assertSame('default-sp', $options->simplesaml_auth_source);
        self::assertObjectNotHasProperty('unknown', $options);
    }

    public function testGetOptionsReadsNetworkSettingsOnMultisite(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\expect('get_site_option')
            ->once()
            ->with('rrze_sso')
            ->andReturn(array('simplesaml_auth_source' => 'network-sp'));

        self::assertSame('network-sp', Options::getOptions()->simplesaml_auth_source);
        self::assertSame('sso', Options::getOptionGroup());
        self::assertSame('rrze_sso', Options::getOptionName());
    }
}
