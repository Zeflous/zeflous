<?php

declare(strict_types=1);

/**
 * Enforces a minimum line-coverage percentage from the Clover report produced by
 * `phpunit --coverage-clover build/clover.xml`.
 *
 * Usage: php scripts/ci/assert-coverage.php [threshold=90]
 */

$threshold = (float) ($argv[1] ?? '90');
$report = dirname(__DIR__, 2) . '/build/clover.xml';

if (!is_file($report)) {
    fwrite(STDERR, sprintf('Coverage report not found: %s%s', $report, PHP_EOL));
    exit(1);
}

$xml = simplexml_load_file($report);

if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(STDERR, 'Unable to read coverage metrics from the Clover report.' . PHP_EOL);
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percentage = $statements > 0 ? ($covered / $statements) * 100.0 : 0.0;

printf('Line coverage: %.2f%% (required: %.2f%%)%s', $percentage, $threshold, PHP_EOL);

if ($percentage < $threshold) {
    fwrite(STDERR, 'Coverage gate FAILED.' . PHP_EOL);
    exit(1);
}

echo 'Coverage gate PASSED.' . PHP_EOL;
