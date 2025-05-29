<?php

declare(strict_types=1);

namespace Atournayre\RectorAutoUpgrade;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Process\Process;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    protected Composer $composer;
    protected IOInterface $io;

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // no-op
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // no-op
    }

    public function onPostUpdate(Event $event): void
    {
        $io = $event->getIO();
        $composer = $event->getComposer();

        if (!$this->isDevelopmentEnvironment()) {
            return;
        }

        if (!class_exists('Rector\Config\RectorConfig') && !file_exists($this->rectorBinPath())) {
            $io->debug('Rector bin path: ' . $this->rectorBinPath());
            $io->debug('Class Rector\Config\RectorConfig exists: ' . class_exists('Rector\Config\RectorConfig') ? 'yes' : 'no');
            $io->write('<warning>Rector is not installed. No automatic updates will be performed.</warning>');
            return;
        }

        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $installationManager = $composer->getInstallationManager();
        $packages = $localRepo->getPackages();

        $upgradesToRun = [];

        foreach ($packages as $package) {
            $version = $package->getFullPrettyVersion();
            $installPath = $installationManager->getInstallPath($package);

            if (null === $installPath) {
                $io->debug(sprintf('Package %s has no install path', $package->getName()));
                continue;
            }

            $rectorSetPath = $this->rectorSetPath($installPath, $version);
            $io->debug(sprintf('Checking for Rector set at %s', $rectorSetPath));

            if (file_exists($rectorSetPath)) {
                $packageName = $package->getName();

                $answer = $io->ask(
                    sprintf(
                        'Do you want to run Rector upgrade for package %s (version %s)? [y/N] ',
                        $packageName,
                        $version,
                    ),
                    'n',
                );

                if (strtolower($answer) === 'y') {
                    $upgradesToRun[] = [
                        'package' => $packageName,
                        'version' => $version,
                        'config_path' => $rectorSetPath,
                    ];
                }
            }
        }

        if (empty($upgradesToRun)) {
            $io->write('<info>No Rector upgrade will be performed.</info>');
            return;
        }

        foreach ($upgradesToRun as $upgrade) {
            $this->runRectorUpgrade($upgrade, $io);
        }
    }

    private function isDevelopmentEnvironment(): bool
    {
        if (file_exists($this->rectorBinPath())) {
            return true;
        }

        if (getenv('APP_ENV') === 'dev') {
            return true;
        }

        if (getenv('APP_ENV') !== 'prod' && getenv('APP_ENV') !== 'production') {
            return true;
        }

        return false;
    }

    private function rectorBinPath(): string
    {
        return $this->absolutePath('/vendor/bin/rector');
    }

    private function absolutePath(string $path): string
    {
        return getcwd() . $path;
    }

    private function rectorUgradePath(string $installPath, string $version): string
    {
        return sprintf('%s/rector/upgrade/%s/rector.php', $installPath, $version);
    }

    private function rectorSetPath(string $installPath, string $version): string
    {
        return sprintf('%s/rector/sets/%s.php', $installPath, $version);
    }

    private function runRectorUpgrade(array $upgrade, IOInterface $io): void
    {
        $packageName = $upgrade['package'];
        $version = $upgrade['version'];
        $configPath = $upgrade['config_path'];

        $io->write(sprintf(
            '<info>Running Rector upgrade for %s (version %s)</info>',
            $packageName,
            $version,
        ));

        $rectorConfigTmpPath = $this->rectorConfigTmpPath($version);
        $tempConfigFile = $this->createTemporaryConfig($configPath, $rectorConfigTmpPath);

        $io->debug(sprintf('<info>Temporary config file: %s</info>', $rectorConfigTmpPath));
        $io->debug(sprintf('<info>Temporary config file exists: %s</info>', file_exists($rectorConfigTmpPath) ? 'yes' : 'no'));

        $process = new Process([
            $this->rectorBinPath(),
            'process',
            '--config',
            $tempConfigFile
        ]);

        $io->write(sprintf('<info>Command: %s</info>', $process->getCommandLine()));

        $process->run();

        $io->write($process->getOutput());

        if ($process->isSuccessful()) {
            @unlink($tempConfigFile);

            $io->write('<info>Upgrade successful for ' . $packageName . '</info>');
            return;
        }

        $io->write('<error>Upgrade failed for ' . $packageName . '</error>');
        $io->write($process->getErrorOutput());
    }

    private function rectorConfigTmpPath(string $version): string
    {
        return sprintf('%s/rector_upgrade_%s.php', sys_get_temp_dir(), $version);
    }

    private function createTemporaryConfig(string $rectorSetPath, string $rectorConfigTmpPath): string
    {
        $currentWorkingDirectory = $this->absolutePath('');

        $config = <<<PHP
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig \$rectorConfig): void {
    \$rectorConfig->sets([
        '{$rectorSetPath}'
    ]);

    \$rectorConfig->paths([
        '{$currentWorkingDirectory}/src',
    ]);
};
PHP;

        file_put_contents($rectorConfigTmpPath, $config);

        return $rectorConfigTmpPath;
    }
}
