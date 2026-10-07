<?php

declare(strict_types=1);

/**
 * Mutation-score floor gate.
 *
 * Reads the JSON log produced by Infection (`build/infection/infection.json`) and
 * asserts that both the mutation score index (MSI) and the covered-code MSI meet
 * the requested floor. The scores are read from Infection's own reported metrics
 * (which correctly account for killed, escaped and timed-out mutants) instead of
 * being recomputed here, so the gate can never diverge from the tool.
 *
 * Fail-closed: a missing report OR missing metric is a failure, never a silent pass.
 *
 * Usage: php scripts/ci/assert-zone-coverage.php [--floor=100]
 */

$floor = 100.0;

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

$contents = file_get_contents($report);
$data = is_string($contents) ? json_decode($contents, true) : null;

if (!is_array($data)) {
    fwrite(STDERR, 'Unable to parse the Infection JSON report.' . PHP_EOL);
    exit(1);
}

$stats = $data['stats'] ?? null;

if (!is_array($stats)) {
    fwrite(STDERR, 'The Infection report does not contain mutation statistics.' . PHP_EOL);
    exit(1);
}

foreach (['msi', 'coveredCodeMsi', 'mutationCodeCoverage'] as $required) {
    if (!array_key_exists($required, $stats)) {
        fwrite(STDERR, sprintf('The Infection report is missing the "%s" metric.%s', $required, PHP_EOL));
        exit(1);
    }
}

$msi = (float) $stats['msi'];
$coveredMsi = (float) $stats['coveredCodeMsi'];
$coverage = (float) $stats['mutationCodeCoverage'];

printf(
    'Mutation score: MSI %.2f%% / covered MSI %.2f%% (code coverage %.2f%%, floor: %.2f%%)%s',
    $msi,
    $coveredMsi,
    $coverage,
    $floor,
    PHP_EOL,
);

if ($msi < $floor || $coveredMsi < $floor) {
    fwrite(STDERR, 'Mutation-score floor FAILED.' . PHP_EOL);
    exit(1);
}

echo 'Mutation-score floor PASSED.' . PHP_EOL;
