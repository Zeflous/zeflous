<?php

declare(strict_types=1);

/**
 * Fast syntax lint over the project source tree.
 *
 * Used by the `composer lint` script and by the `ci` pipeline.
 */

$root = dirname(__DIR__);
$directories = ['src', 'tests', 'benchmarks'];
$failures = [];
$checked = 0;

foreach ($directories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;

    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        ++$checked;

        $output = [];
        $exitCode = 0;
        exec(
            sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file->getPathname())),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            $failures[] = sprintf('%s%s%s', $file->getPathname(), PHP_EOL, implode(PHP_EOL, $output));
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Syntax errors detected:" . PHP_EOL . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf('Lint OK: %d PHP files checked.%s', $checked, PHP_EOL));
