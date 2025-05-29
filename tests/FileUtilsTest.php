<?php

declare(strict_types=1);

namespace Atournayre\RectorAutoUpgrade\Tests;

use Atournayre\RectorAutoUpgrade\ComposerPlugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class FileUtilsTest extends TestCase
{
    private ComposerPlugin $plugin;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->plugin = new ComposerPlugin();
        $this->reflection = new ReflectionClass($this->plugin);
    }

    public function testRectorBinPath(): void
    {
        $method = $this->reflection->getMethod('rectorBinPath');
        $method->setAccessible(true);

        $expected = getcwd() . '/vendor/bin/rector';

        $this->assertEquals($expected, $method->invoke($this->plugin));
    }

    public function testRectorUpgradePath(): void
    {
        $method = $this->reflection->getMethod('rectorUgradePath');
        $method->setAccessible(true);

        $installPath = '/tmp/test-package';
        $version = '1.2.3';
        $expected = '/tmp/test-package/rector/upgrade/1.2.3/rector.php';

        $this->assertEquals($expected, $method->invoke($this->plugin, $installPath, $version));
    }

    public function testRectorSetPath(): void
    {
        $method = $this->reflection->getMethod('rectorSetPath');
        $method->setAccessible(true);

        $installPath = '/tmp/test-package';
        $version = '1.2.3';
        $expected = '/tmp/test-package/rector/sets/1.2.3.php';

        $this->assertEquals($expected, $method->invoke($this->plugin, $installPath, $version));
    }

    public function testCreateTemporaryConfig(): void
    {
        $method = $this->reflection->getMethod('createTemporaryConfig');
        $method->setAccessible(true);

        // Create a test set path
        $testSetPath = '/path/to/rector/sets/1.2.3.php';
        $tempPath = sys_get_temp_dir() . '/rector-temp-config.php';

        try {
            $result = $method->invoke($this->plugin, $testSetPath, $tempPath);

            $this->assertEquals($tempPath, $result);
            $this->assertFileExists($tempPath);

            $content = file_get_contents($tempPath);
            $this->assertStringContainsString('use Rector\Config\RectorConfig;', $content);
            $this->assertStringContainsString('$rectorConfig->sets([', $content);
            $this->assertStringContainsString("'{$testSetPath}'", $content);
            $this->assertStringContainsString(getcwd(), $content);
        } finally {
            // Clean up
            @unlink($tempPath);
        }
    }
}
