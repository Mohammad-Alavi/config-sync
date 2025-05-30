<?php

declare(strict_types=1);

use MohammadAlavi\ConfigSync\ConfigSyncPlugin;

it('generates config-sync.json with defaults and schema pointer', function (): void {
    // Arrange: fresh temp dir
    $tmp = sys_get_temp_dir() . '/config-sync-test-' . bin2hex(random_bytes(4));
    if (!mkdir($tmp, 0777, true) && !is_dir($tmp)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $tmp));
    }

    // path to the CLI executable inside the package repo
    $bin = dirname(__DIR__, 3) . '/bin/config-sync';
    expect($bin)->not->toBeFalse();

    // Act: run `config-sync init` in the temp project
    $cmd = sprintf('php %s init', escapeshellarg($bin));
    exec($cmd, $output, $status);

    // Assert
    expect($status)->toBe(0);
    $configFile = $tmp . '/config-sync.json';
    var_dump($configFile);
    expect(is_file($configFile))->toBeTrue();

    $data = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    expect($data)->toHaveKey('$schema', ConfigSyncPlugin::SCHEMA_URL)
        ->and($data['paths']['phpunit_cache'])->toBe('temp/phpunit');

    // Running the command again should abort with non‑zero exit code
    exec($cmd, $o2, $statusAgain);
    expect($statusAgain)->toBeGreaterThan(0);

    // Cleanup
    unlink($configFile);
    rmdir($tmp);
});
