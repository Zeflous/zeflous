<?php

declare(strict_types=1);

/**
 * PHPStan "no new debt" ratchet.
 *
 * The framework must start from a clean baseline: a committed
 * `phpstan-baseline.neon` that ignores errors is not accepted, because it hides
 * regressions. Until the source tree reaches zero errors organically, this gate
 * fails whenever a non-empty baseline is committed.
 *
 * Usage: php scripts/ci/assert-phpstan-baseline.php
 */

$baseline = dirname(__DIR__, 2) . '/phpstan-baseline.neon';

if (!is_file($baseline)) {
    echo 'PHPStan ratchet PASSED: no baseline file present (zero accepted errors).' . PHP_EOL;
    exit(0);
}

$baselineContents = (string) file_get_contents($baseline);
$ignoredErrors = preg_match_all('/^\s*message\s*:/m', $baselineContents);

if ($ignoredErrors > 0) {
    fwrite(
        STDERR,
        sprintf(
            'PHPStan ratchet FAILED: phpstan-baseline.neon ignores %d error(s); fix them instead of baselining.%s',
            $ignoredErrors,
            PHP_EOL,
        ),
    );
    exit(1);
}

echo 'PHPStan ratchet PASSED: baseline is empty.' . PHP_EOL;
