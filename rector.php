<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/benchmarks',
    ])
    ->withSkip([
        __DIR__ . '/src/Contracts/Psr',
        __DIR__ . '/vendor',
        // PHP's stream-wrapper protocol fixes the fixture methods' signatures
        // (stream_open(stream read, ...)); the protocol's unused parameters
        // must not be pruned away.
        __DIR__ . '/tests/Support/Http',
        // Kept authoritative by php-cs-fixer; avoid a Rector/CS-Fixer tug-of-war.
        'Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    );
