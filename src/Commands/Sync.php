<?php

namespace MohammadAlavi\ConfigSync\Commands;

use Composer\Command\BaseCommand;
use Composer\InstalledVersions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Composer plugin that keeps project‑wide tooling configs in sync.
 */
final class Sync extends BaseCommand
{
    private array $config;

    public function __construct()
    {
        parent::__construct();
        $configPath = __DIR__ . '/../config-sync.json';
        if (!file_exists($configPath)) {
            throw new \RuntimeException('config-sync.json file not found.');
        }

        $this->config = json_decode(
            file_get_contents(
                $configPath,
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    protected function configure(): void
    {
        $this->setName('config-sync:sync')
            ->setDescription('Sync project configuration files with the stubs provided by the package.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->sync();

        return 0;
    }

    /**
     * Copy / render every stub that applies to the current project.
     */
    public function sync(): void
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
                'PHP_CS_FIXER_IN' => $config['phpCsFixer']['in'],
                'PHP_CS_FIXER_NOT_NAME' => $config['phpCsFixer']['notName'],
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
            $stub = $this->stubPath('phpunit.xml.stub');
            $dest = $root . '/phpunit.xml';

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
                $this->getIO()->writeError('<warning>config-sync.json is invalid – ' . $e->getMessage() . '</warning>');
            }
        }

        return $this->config;
    }

    private function hasComposerPkg(string $name): bool
    {
        return InstalledVersions::isInstalled($name)
            || isset($this->requireComposer()->getPackage()->getRequires()[$name]);
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
        return realpath(__DIR__ . '/../..') . '/' . ltrim($relative, '/');
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
            $this->getIO()->writeError('<warning>Stub missing: ' . $stub . '</warning>');

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
