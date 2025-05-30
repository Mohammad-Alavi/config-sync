<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use MohammadAlavi\ConfigSync\ConfigSyncPlugin;
use Symfony\Component\Filesystem\Filesystem;

uses()->group('config-sync');

/**
 * Helper to activate the plugin with mocked Composer + IO.
 */
function createPlugin(Mockery\MockInterface $composer, Mockery\MockInterface $io): ConfigSyncPlugin
{
    $plugin = new ConfigSyncPlugin();
    $plugin->activate($composer, $io);

    return $plugin;
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
    $stubDir = dirname((new ReflectionClass(ConfigSyncPlugin::class))->getFileName(), 2) . '/stubs';
    if (!is_dir($stubDir) && !mkdir($stubDir, 0777, true) && !is_dir($stubDir)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $stubDir));
    }
    file_put_contents($stubDir . '/php-cs-fixer.dist.php', "<?php // stub\n");
    file_put_contents($stubDir . '/.eslintrc.cjs', "{}\n");
});

afterEach(function (): void {
    chdir($this->origCwd);
    (new Filesystem())->remove($this->tmpDir);
    Mockery::close();
});

it('copies php-cs-fixer stub when the package is required', function (): void {
    $package = Mockery::mock(PackageInterface::class);
    $package->allows('getRequires')->andReturn(['friendsofphp/php-cs-fixer' => true]);

    $composer = Mockery::mock(Composer::class);
    $composer->allows('getPackage')->andReturn($package);

    $io = Mockery::mock(IOInterface::class);

    $plugin = createPlugin($composer, $io);

    $plugin->sync(new Event('post-install-cmd', $composer, $io, false));

    expect(file_exists($this->tmpDir . '/.php-cs-fixer.dist.php'))->toBeTrue();
});

it('copies eslint stub when package.json contains eslint devDependency', function (): void {
    // Create package.json that requires eslint
    file_put_contents($this->tmpDir . '/package.json', json_encode([
        'devDependencies' => [
            'eslint' => '^8.0.0',
        ],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    extracted();

    expect(file_exists($this->tmpDir . '/.eslintrc.cjs'))->toBeTrue();
});

function extracted(): void
{
    $package = Mockery::mock(PackageInterface::class);
    $package->allows('getRequires')->andReturn([]);

    $composer = Mockery::mock(Composer::class);
    $composer->allows('getPackage')->andReturn($package);

    $io = Mockery::mock(IOInterface::class);

    $plugin = createPlugin($composer, $io);

    // Should complete without exceptions
    $plugin->sync(new Event('post-install-cmd', $composer, $io, false));
}

it('does not throw if package.json is absent', function (): void {
    extracted();

    expect(true)->toBeTrue();
});
