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
    //    public const SCHEMA_URL = 'https://github.com/Mohammad-Alavi/config-schema/raw/main/src/config-sync.schema.json';
    public const SCHEMA_URL = 'vendor/mohammad-alavi/config-sync/config-sync.schema.json';

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
            $stub = $this->stubPath('.php-cs-fixer.dist.php.stub');
            $dest = $root . '/.php-cs-fixer.dist.php';

            $this->copyStub($stub, $dest, [
                'PHP_CS_FIXER_CACHE_FILE' => $config['paths']['php_cs_fixer_cache'],
            ], $fs);
        }

        /* ------------------------------------------------------------------ */
        /*  ESLint */
        /* ------------------------------------------------------------------ */
        $pkgJsonPath = $root . '/package.json';
        if (is_file($pkgJsonPath)) {
            $pkg = json_decode(file_get_contents($pkgJsonPath), true);
            if (($pkg['devDependencies']['eslint'] ?? null) !== null) {
                $fs->copy($this->stubPath('.eslintrc.json.stub'), $root . '/.eslintrc.json', true);
            }
        }

        /* ------------------------------------------------------------------ */
        /*  PHPUnit */
        /* ------------------------------------------------------------------ */
        if ($this->hasComposerPkg('phpunit/phpunit')) {
            $stub = $this->stubPath('phpunit.xml.dist.stub');
            $dest = $root . '/phpunit.xml.dist';

            $this->copyStub($stub, $dest, [
                'PHPUNIT_CACHE_DIR' => $config['paths']['phpunit_cache'],
            ], $fs);
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

    /** Helper for stubs/ directory. */
    private function stubPath(string $file): string
    {
        return $this->packagePath('stubs/' . ltrim($file, '/'));
    }

    /**
     * Absolute path to any file inside the package, regardless of where the
     * package lives in the vendor tree.
     */
    private function packagePath(string $relative): string
    {
        return realpath(__DIR__ . '/..') . '/' . ltrim($relative, '/');
    }

    /**
     * Copy a stub after rendering template vars.  Logs a warning instead of
     * throwing if the stub is missing.
     *
     * @param array<string,string> $vars
     */
    private function copyStub(string $stub, string $dest, array $vars, Filesystem $fs): void
    {
        if (!is_file($stub)) {
            $this->io->writeError('<warning>Stub missing: ' . $stub . '</warning>');

            return;
        }

        $fs->dumpFile($dest, $this->renderTemplate($stub, $vars));
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
