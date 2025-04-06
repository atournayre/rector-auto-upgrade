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

        if (!$this->isDevelopmentEnvironment()) {
            return;
        }

        if (!class_exists('Rector\Core\Configuration\Configuration')) {
            $io->write('<info>To do so, run: composer require rector/rector</info>');
            return;
        }

        var_dump([
            'composer' => [
                'name' => $event->getComposer()->getPackage()->getName(),
                'version' => $event->getComposer()->getPackage()->getVersion(),
                'fullPrettyVersion' => $event->getComposer()->getPackage()->getFullPrettyVersion(),
            ],
        ]);
        $event->getComposer()->getPackage()->getVersion();
    }

    private function isDevelopmentEnvironment(): bool
    {
        $composerJson = json_decode(file_get_contents(getcwd() . '/composer.json'), true);
        if (isset($composerJson['require-dev']['rector/rector'])) {
            return true;
        }

        if (getenv('APP_ENV') === 'dev') {
            return true;
        }

        global $argv;
        if (in_array('--dev', $argv, true)) {
            return true;
        }

        return false;
    }
}
