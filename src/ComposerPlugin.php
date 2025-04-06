<?php

declare(strict_types=1);

namespace Atournayre\RectorAutoUpgrade;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    protected Composer $composer;
    protected IOInterface $io;

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

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate',
        ];
    }

    public function onPostUpdate(Event $event): void
    {
        $io = $event->getIO();
        $composer = $event->getComposer();

        if (!$this->isDevelopmentEnvironment()) {
            return;
        }

        if (!class_exists('Rector\Config\RectorConfig') && !file_exists('vendor/bin/rector')) {
            $io->write('<warning>Rector is not installed. No automatic updates will be performed.</warning>');
            return;
        }

        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $installationManager = $composer->getInstallationManager();
        $packages = $localRepo->getPackages();

        $upgradesToRun = [];

        foreach ($packages as $package) {
            $packageName = $package->getName();
            $version = $package->getFullPrettyVersion();
            $installPath = $installationManager->getInstallPath($package);

            $rectorConfigPath = $installPath . '/rector/upgrade/' . $version . '/rector.php';
            $io->debug(sprintf('Checking for upgrade file at %s', $rectorConfigPath));

            if (file_exists($rectorConfigPath)) {
                $answer = $io->ask(
                    sprintf(
                        'Do you want to run Rector upgrade for package %s (version %s)? [y/N] ',
                        $packageName,
                        $version
                    ),
                    'n'
                );

                if (strtolower($answer) === 'y') {
                    $upgradesToRun[] = [
                        'package' => $packageName,
                        'version' => $version,
                        'config_path' => $rectorConfigPath,
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

        if (file_exists('vendor/bin/rector')) {
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

        $tempConfigFile = $this->createTemporaryConfig($configPath);

        $command = sprintf('vendor/bin/rector process --config %s', $tempConfigFile);
        $io->write(sprintf('<info>Command: %s</info>', $command));

        exec($command, $output, $returnCode);

        @unlink($tempConfigFile);

        if ($returnCode !== 0) {
            $io->write('<error>Upgrade failed for ' . $packageName . '</error>');
            $io->write(implode("\n", $output));
        } else {
            $io->write('<info>Upgrade successful for ' . $packageName . '</info>');
        }
    }

    private function createTemporaryConfig(string $packageConfigPath): string
    {
        $tempFile = sys_get_temp_dir() . '/rector_upgrade_' . uniqid() . '.php';
        $src = getcwd() . '/src';

        $config = <<<PHP
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig \$rectorConfig): void {
    \$rectorConfig->paths([
        '{$src}',
    ]);
    
    require_once '{$packageConfigPath}';
};
PHP;

        file_put_contents($tempFile, $config);

        return $tempFile;
    }
}
