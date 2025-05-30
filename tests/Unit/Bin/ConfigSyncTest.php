<?php

declare(strict_types=1);

it('generates config-sync.json with defaults and schema pointer', function (): void {
    // --------------------------------------------------------------------
    //  Arrange: create a truly isolated playground in the system tmp dir
    // --------------------------------------------------------------------
    $tmp = sys_get_temp_dir() . '/config-sync-test-' . bin2hex(random_bytes(4));
    if (!mkdir($tmp, 0777, true) && !is_dir($tmp)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $tmp));
    }

    // ensure directory as well as all nested files are removed *even if* the
    // test halts on an earlier failed assertion or fatal error.
    $cleanup = static function (string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        @rmdir($dir);
    };
    register_shutdown_function($cleanup, $tmp);

    // --------------------------------------------------------------------
    //  Act: run the real CLI helper from *inside* the temp dir
    // --------------------------------------------------------------------
    $bin = dirname(__DIR__, 3) . '/bin/config-sync';
    exec(sprintf('cd %s && php %s init 2>&1', escapeshellarg($tmp), $bin), $out1, $code1);

    // --------------------------------------------------------------------
    //  Assert: first run succeeds and writes a valid JSON file with defaults
    // --------------------------------------------------------------------
    expect($code1)->toBe(0);

    $cfg = $tmp . '/config-sync.json';
    expect(file_exists($cfg))->toBeTrue();

    $json = json_decode(file_get_contents($cfg), true, 512, JSON_THROW_ON_ERROR);

    expect($json)
        ->toHaveKey('$schema', 'https://github.com/Mohammad-Alavi/config-sync/raw/main/src/config-sync.schema.json')
        ->and($json['paths']['source'] ?? null)->toBe('src')
        ->and($json['paths']['stubs'] ?? null)->toBe('stubs');

    // --------------------------------------------------------------------
    //  Assert: second run stops with exit‑code 1 because file already exists
    // --------------------------------------------------------------------
    exec(sprintf('cd %s && php %s init 2>&1', escapeshellarg($tmp), $bin), $out2, $code2);
    expect($code2)->toBe(1);
});
