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

    public function testCreateTemporaryConfig(): void
    {
        $method = $this->reflection->getMethod('createTemporaryConfig');
        $method->setAccessible(true);

        // Create a test config file
        $testContent = '<?php return static function () { return ["%currentWorkingDirectory%" => "should-be-replaced"]; };';
        $testConfigPath = sys_get_temp_dir() . '/rector-test-config.php';
        file_put_contents($testConfigPath, $testContent);

        $tempPath = sys_get_temp_dir() . '/rector-temp-config.php';

        try {
            $result = $method->invoke($this->plugin, $testConfigPath, $tempPath);

            $this->assertEquals($tempPath, $result);
            $this->assertFileExists($tempPath);

            $content = file_get_contents($tempPath);
            $this->assertStringContainsString(getcwd(), $content);
            $this->assertStringNotContainsString('%currentWorkingDirectory%', $content);
        } finally {
            // Clean up
            @unlink($testConfigPath);
            @unlink($tempPath);
        }
    }
}
