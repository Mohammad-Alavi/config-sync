<?php

namespace MohammadAlavi\ConfigSync;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Composer plugin that keeps project‑wide tooling configs in sync.
 */
final class ConfigSyncPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Default values consumed by the stub templates.
     * Users may override these inside their root `config-sync.json`.
     */
    public const DEFAULT_CONFIG = [
        'paths' => [
            'phpunit_cache' => 'temp/phpunit',                // <PHPUNIT_CACHE_DIR>
            'php_cs_fixer_cache' => "__DIR__ . '/temp/.php-cs-fixer.cache'",    // <PHP_CS_FIXER_CACHE_FILE>
        ],
    ];

    /**
     * Relative schema URI injected into `config-sync.json` by the init helper.
     */
    public const SCHEMA_URL = 'vendor/company/config-sync/config-sync.schema.json';

    private Composer $composer;
    private IOInterface $io;

    /* --------------------------------------------------------------------- */
    /*  Composer lifecycle */
    /* --------------------------------------------------------------------- */

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

    /**
     * Copy / render every stub that applies to the current project.
     */
    public function sync(Event $event): void
    {
        $root = getcwd();
        $fs = new Filesystem();
        $config = $this->loadConfig($root);

        /* ------------------------------------------------------------------ */
        /*  php‑cs‑fixer */
        /* ------------------------------------------------------------------ */
        if ($this->hasComposerPkg('friendsofphp/php-cs-fixer')) {
            $stub = __DIR__ . '/../stubs/.php-cs-fixer.dist.php.stub';
            $dest = $root . '/.php-cs-fixer.dist.php';

            $fs->dumpFile($dest, $this->renderTemplate($stub, [
                'PHP_CS_FIXER_CACHE_FILE' => $config['paths']['php_cs_fixer_cache'],
            ]));
        }

        /* ------------------------------------------------------------------ */
        /*  ESLint */
        /* ------------------------------------------------------------------ */
        $pkgJsonPath = $root . '/package.json';
        if (is_file($pkgJsonPath)) {
            $pkg = json_decode(file_get_contents($pkgJsonPath), true);
            if (($pkg['devDependencies']['eslint'] ?? null) !== null) {
                $fs->copy(__DIR__ . '/../stubs/.eslintrc.json.stub', $root . '/.eslintrc.json', true);
            }
        }

        /* ------------------------------------------------------------------ */
        /*  PHPUnit */
        /* ------------------------------------------------------------------ */
        if ($this->hasComposerPkg('phpunit/phpunit')) {
            $stub = __DIR__ . '/../stubs/phpunit.xml.dist.stub';
            $dest = $root . '/phpunit.xml.dist';

            $fs->dumpFile($dest, $this->renderTemplate($stub, [
                'PHPUNIT_CACHE_DIR' => $config['paths']['phpunit_cache'],
            ]));
        }
    }

    /* --------------------------------------------------------------------- */
    /*  Helpers */
    /* --------------------------------------------------------------------- */

    /**
     * Merge user overrides (if any) with defaults.
     */
    private function loadConfig(string $root): array
    {
        $config = self::DEFAULT_CONFIG;
        $userFile = $root . '/config-sync.json';

        if (is_file($userFile)) {
            try {
                $user = json_decode(file_get_contents($userFile), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($user)) {
                    $config = array_replace_recursive($config, $user);
                }
            } catch (\JsonException $e) {
                $this->io->writeError('<warning>config-sync.json is invalid – ' . $e->getMessage() . '</warning>');
            }
        }

        return $config;
    }

    private function hasComposerPkg(string $name): bool
    {
        return InstalledVersions::isInstalled($name)
            || isset($this->composer->getPackage()->getRequires()[$name]);
    }

    /**
     * Replace {{PLACEHOLDERS}} inside the given stub with their real values.
     *
     * @param array<string,string> $vars
     */
    private function renderTemplate(string $stubPath, array $vars): string
    {
        $content = file_get_contents($stubPath);

        foreach ($vars as $key => $value) {
            $content = str_replace('{{' . strtoupper($key) . '}}', $value, $content);
        }

        return $content;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
