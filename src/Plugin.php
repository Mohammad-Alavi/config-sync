<?php

namespace MohammadAlavi\ConfigSync;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

final class Plugin implements PluginInterface, Capable, EventSubscriberInterface
{
    private Composer $composer;

    public static function getSubscribedEvents(): array
    {
        return [
            'post-autoload-dump' => 'registerPlugins',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $io->write('Activating ConfigSyncPlugin...');
        $this->composer = $composer;
    }

    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => CommandProvider::class,
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public function registerPlugins(): void
    {
        \Safe\fwrite(STDERR, "Registering ConfigSyncPlugin...\n");
        $cmd = new Command();
        $cmd->setComposer($this->composer);
        $cmd->run(new ArrayInput([]), new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, true));
    }
}
