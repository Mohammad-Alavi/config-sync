<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Package\PackageInterface;
use MohammadAlavi\ConfigSync\Command;
use Symfony\Component\Filesystem\Filesystem;

describe(basename(Command::class), function (): void {
    /**
     * Helper to activate the plugin with mocked Composer + IO.
     */
    function createPlugin(Mockery\MockInterface $composer): Command
    {
        return new Command($composer);
    }

    beforeEach(function (): void {
        // Temporary working directory for each test
        $this->tmpDir = sys_get_temp_dir() . '/config-sync-' . uniqid('', true);
        if (!mkdir($concurrentDirectory = $this->tmpDir, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        $this->origCwd = getcwd();
        chdir($this->tmpDir);

        // Ensure stub files exist next to the plugin source (src/../stubs)
        $stubDir = dirname((new ReflectionClass(Command::class))->getFileName(), 2) . '/stubs';
        if (!is_dir($stubDir) && !mkdir($stubDir, 0777, true) && !is_dir($stubDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $stubDir));
        }
    });

    afterEach(function (): void {
        chdir($this->origCwd);
        (new Filesystem())->remove($this->tmpDir);
        Mockery::close();
    });

    it('copies .php-cs-fixer stub when the package is required', function (): void {
        $package = Mockery::mock(PackageInterface::class);
        $package->allows('getRequires')->andReturn(['friendsofphp/.php-cs-fixer' => true]);

        $composer = Mockery::mock(Composer::class);
        $composer->allows('getPackage')->andReturn($package);

        $plugin = createPlugin($composer);

        $plugin->sync();

        expect(file_exists($this->tmpDir . '/.php-cs-fixer.dist.php'))->toBeTrue()
            ->and(file_get_contents($this->tmpDir . '/.php-cs-fixer.dist.php'))
            ->toBe(file_get_contents(dirname(__DIR__, 2) . '/.php-cs-fixer.dist.php'));
    });

    it('copies eslint stub when package.json contains eslint devDependency', function (): void {
        // Create package.json that requires eslint
        file_put_contents($this->tmpDir . '/package.json', json_encode([
            'devDependencies' => [
                'eslint' => '^8.0.0',
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        extracted();

        expect(file_exists($this->tmpDir . '/.eslintrc.json'))->toBeTrue()
            ->and(file_get_contents($this->tmpDir . '/.eslintrc.json'))
            ->toBe(file_get_contents(dirname(__DIR__, 2) . '/.eslintrc.json'));
    });

    function extracted(): void
    {
        $package = Mockery::mock(PackageInterface::class);
        $package->allows('getRequires')->andReturn([]);

        $composer = Mockery::mock(Composer::class);
        $composer->allows('getPackage')->andReturn($package);

        $plugin = createPlugin($composer);

        // Should complete without exceptions
        $plugin->sync();
    }

    it('does not throw if package.json is absent', function (): void {
        extracted();

        expect(true)->toBeTrue();
    });
})->covers(Command::class);
