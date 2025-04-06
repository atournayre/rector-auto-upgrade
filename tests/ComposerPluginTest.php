<?php

declare(strict_types=1);

namespace Atournayre\RectorAutoUpgrade\Tests;

use Atournayre\RectorAutoUpgrade\ComposerPlugin;
use Composer\Composer;
use Composer\IO\IOInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ComposerPluginTest extends TestCase
{
    private ComposerPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new ComposerPlugin();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ComposerPlugin::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey('post-update-cmd', $events);
        $this->assertEquals('onPostUpdate', $events['post-update-cmd']);
    }

    /**
     * @throws Exception
     */
    public function testActivate(): void
    {
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        $this->plugin->activate($composer, $io);

        $reflection = new ReflectionClass($this->plugin);

        $composerProp = $reflection->getProperty('composer');
        $composerProp->setAccessible(true);

        $ioProp = $reflection->getProperty('io');
        $ioProp->setAccessible(true);

        $this->assertSame($composer, $composerProp->getValue($this->plugin));
        $this->assertSame($io, $ioProp->getValue($this->plugin));
    }

    public function testIsDevelopmentEnvironment(): void
    {
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('isDevelopmentEnvironment');
        $method->setAccessible(true);

        // Test with APP_ENV=dev
        $originalEnv = getenv('APP_ENV');
        putenv('APP_ENV=dev');

        $this->assertTrue($method->invoke($this->plugin));

        // Restore environment
        if ($originalEnv !== false) {
            putenv("APP_ENV=$originalEnv");
        } else {
            putenv('APP_ENV');
        }
    }

    public function testAbsolutePath(): void
    {
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('absolutePath');
        $method->setAccessible(true);

        $path = '/test/path';
        $expected = getcwd() . $path;

        $this->assertEquals($expected, $method->invoke($this->plugin, $path));
    }

    public function testRectorConfigTmpPath(): void
    {
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('rectorConfigTmpPath');
        $method->setAccessible(true);

        $version = '1.0.0';
        $expected = sprintf('%s/rector_upgrade_%s.php', sys_get_temp_dir(), $version);

        $this->assertEquals($expected, $method->invoke($this->plugin, $version));
    }
}
