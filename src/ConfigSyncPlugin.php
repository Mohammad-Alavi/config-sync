<?php

namespace MohammadAlavi\ConfigSync;

use Composer\Composer;
use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Composer plugin that keeps project‑wide tooling configs in sync.
 */
final class ConfigSyncPlugin
{
    private array $config;
    private Composer $composer;
    private IOInterface $io;

    public function __construct()
    {
        $configPath = __DIR__ . '/config-sync.json'; // Adjust path accordingly
        if (!file_exists($configPath)) {
            throw new \RuntimeException('config-sync.json file not found.');
        }

        $json = file_get_contents($configPath);
        $this->config = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
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
                'PHP_CS_FIXER_CACHE_FILE' => $config['php_cs_fixer']['cache_file'],
            ], $fs);
        }

        /* ------------------------------------------------------------------ */
        /*  ESLint */
        /* ------------------------------------------------------------------ */
        $pkgJsonPath = $root . '/package.json';
        if (is_file($pkgJsonPath)) {
            $pkg = json_decode(file_get_contents($pkgJsonPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_null($pkg['devDependencies']['eslint'] ?? null)) {
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
                'PHPUNIT_CACHE_DIR' => $config['phpunit']['cache_dir'],
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
        $userFile = $root . '/config-sync.json';

        if (is_file($userFile)) {
            try {
                $user = json_decode(file_get_contents($userFile), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($user)) {
                    $this->config = array_replace_recursive($this->config, $user);
                }
            } catch (\JsonException $e) {
                $this->io->writeError('<warning>config-sync.json is invalid – ' . $e->getMessage() . '</warning>');
            }
        }

        return $this->config;
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
}
