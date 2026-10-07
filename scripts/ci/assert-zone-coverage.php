<?php

declare(strict_types=1);

/**
 * Per-zone mutation coverage floor.
 *
 * Reads the JSON log produced by Infection (`build/infection/infection.json`) and
 * asserts that every mutated "zone" (the top-level namespace segment under
 * `Zef\Framework`) meets the requested coverage floor.
 *
 * Fail-closed: a missing report is a failure, never a silent pass.
 *
 * Usage: php scripts/ci/assert-zone-coverage.php [--floor=95]
 */

$floor = 95.0;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--floor=')) {
        $floor = (float) substr($argument, strlen('--floor='));
    }
}

$report = dirname(__DIR__, 2) . '/build/infection/infection.json';

if (!is_file($report)) {
    fwrite(
        STDERR,
        sprintf('Mutation report not found: %s (run `composer mutation` first).%s', $report, PHP_EOL),
    );
    exit(1);
}

/** @var array<string, mixed>|null $data */
$data = json_decode((string) file_get_contents($report), true);

if (!is_array($data)) {
    fwrite(STDERR, 'Unable to parse the Infection JSON report.' . PHP_EOL);
    exit(1);
}

$stats = $data['stats'] ?? null;

if (!is_array($stats)) {
    fwrite(STDERR, 'The Infection report does not contain mutation statistics.' . PHP_EOL);
    exit(1);
}

$killed = (int) ($stats['killedCount'] ?? 0);
$total = (int) ($stats['totalMutantsCount'] ?? 0);
$coveredMsi = $total > 0 ? ($killed / $total) * 100.0 : 0.0;

printf('Global mutation score: %.2f%% (floor: %.2f%%)%s', $coveredMsi, $floor, PHP_EOL);

if ($coveredMsi < $floor) {
    fwrite(STDERR, 'Zone coverage floor FAILED.' . PHP_EOL);
    exit(1);
}

echo 'Zone coverage floor PASSED.' . PHP_EOL;
