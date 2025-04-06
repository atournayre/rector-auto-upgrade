<?php

declare(strict_types=1);

namespace Atournayre\RectorAutoUpgrade\Tests;

use Atournayre\RectorAutoUpgrade\ComposerPlugin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EnvironmentTest extends TestCase
{
    private ComposerPlugin $plugin;
    private ?string $originalAppEnv;

    protected function setUp(): void
    {
        $this->plugin = new ComposerPlugin();
        $this->originalAppEnv = getenv('APP_ENV') ?: null;
    }

    protected function tearDown(): void
    {
        if ($this->originalAppEnv !== null) {
            putenv("APP_ENV={$this->originalAppEnv}");
        } else {
            putenv('APP_ENV');
        }
    }

    #[DataProvider('environmentProvider')]
    public function testEnvironmentDetection(string $env, bool $expected): void
    {
        putenv("APP_ENV={$env}");

        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('isDevelopmentEnvironment');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invoke($this->plugin));
    }

    public static function environmentProvider(): array
    {
        return [
            'dev environment' => ['dev', true],
            'development environment' => ['development', true],
            'local environment' => ['local', true],
            'test environment' => ['test', true],
            'staging environment' => ['staging', true],
            'production environment' => ['prod', false],
            'prod environment' => ['production', false],
        ];
    }
}
