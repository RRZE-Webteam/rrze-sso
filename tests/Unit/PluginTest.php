<?php

namespace RRZE\SSO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use RRZE\SSO\Plugin;
use RRZE\SSO\Tests\TestCase;

#[CoversClass(Plugin::class)]
class PluginTest extends TestCase
{
    public function testLoadedPopulatesPathsUrlsAndMetadata(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        /** @var Plugin $plugin */
        $plugin = $reflection->newInstanceWithoutConstructor();
        $file = '/srv/wp-content/plugins/rrze-sso/rrze-sso.php';
        $reflection->getProperty('pluginFile')->setValue($plugin, $file);

        Functions\when('plugin_basename')->justReturn('rrze-sso/rrze-sso.php');
        Functions\when('plugin_dir_path')->justReturn('/srv/wp-content/plugins/rrze-sso/');
        Functions\when('plugin_dir_url')->justReturn('https://example.org/wp-content/plugins/rrze-sso/');
        Functions\when('sanitize_title')->alias(static fn(string $value): string => strtolower($value));
        Functions\when('get_plugin_data')->justReturn(array(
            'Name' => 'RRZE SSO',
            'Version' => '2.0.0',
            'RequiresWP' => '6.4',
            'RequiresPHP' => '8.2',
        ));

        $plugin->loaded();

        self::assertSame($file, $plugin->getFile());
        self::assertSame('rrze-sso/rrze-sso.php', $plugin->getBasename());
        self::assertSame('/srv/wp-content/plugins/rrze-sso/', $plugin->getDirectory());
        self::assertSame('/srv/wp-content/plugins/rrze-sso/templates/', $plugin->getPath('/templates/'));
        self::assertSame('https://example.org/wp-content/plugins/rrze-sso/assets/', $plugin->getUrl('assets'));
        self::assertSame('rrze-sso', $plugin->getSlug());
        self::assertSame('RRZE SSO', $plugin->getData()['Name']);
        self::assertSame('RRZE SSO', $plugin->getName());
        self::assertSame('2.0.0', $plugin->getVersion());
        self::assertSame('6.4', $plugin->getRequiresWP());
        self::assertSame('8.2', $plugin->getRequiresPHP());
        self::assertNull($plugin->undefinedMethod('argument'));
    }
}
