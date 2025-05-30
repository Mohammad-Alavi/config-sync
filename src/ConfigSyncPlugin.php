<?php

namespace MohammadAlavi\ConfigSync;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

final class ConfigSyncPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'sync',
            'post-update-cmd' => 'sync',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function sync(Event $event): void
    {
        $root = getcwd();
        $fs = new Filesystem();

        // Example rule: only drop php-cs-fixer stub if the fixer is required
        if ($this->hasComposerPkg('friendsofphp/php-cs-fixer')) {
            $fs->copy(__DIR__ . '/../stubs/.php-cs-fixer.dist.php', $root . '/.php-cs-fixer.dist.php', true);
        }

        // Example rule: only drop ESLint stub if npm has eslint
        $pkgJsonPath = $root . '/package.json';
        if (is_file($pkgJsonPath)) {
            $pkgJson = json_decode(file_get_contents($pkgJsonPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_null($pkgJson['devDependencies']['eslint'] ?? null)) {
                $fs->copy(__DIR__ . '/../stubs/.eslintrc.json', $root . '/.eslintrc.json', true);
            }
        }
    }

    private function hasComposerPkg(string $name): bool
    {
        return InstalledVersions::isInstalled($name)
            || isset($this->composer->getPackage()->getRequires()[$name]);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
