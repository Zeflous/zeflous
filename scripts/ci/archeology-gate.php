<?php

declare(strict_types=1);

/**
 * PhpCodeArcheology SARIF post-processor and gate.
 *
 * Two jobs, both required for GitHub Code Scanning to be useful here:
 *
 *  1. NORMALISE PATHS. PhpCodeArcheology writes absolute paths
 *     (`/home/runner/work/<repo>/<repo>/src/...`) with a `%SRCROOT%` base id
 *     that it never defines. GitHub Code Scanning only accepts URIs relative
 *     to the repository root, so the absolute prefix is stripped and the
 *     dangling `uriBaseId` removed.
 *
 *  2. GATE ON ERROR-LEVEL FINDINGS. The tool's own `--fail-on=error` counts
 *     EVERY finding as an error, including the relative "effort/MI/LCOM more
 *     than 30% above/below average" warnings, which are a distribution
 *     artefact (any codebase of this size has entities above 1.3x the mean by
 *     construction) rather than defects. The SARIF `level` field is the
 *     granular signal: it is `error` only for genuine architecture defects
 *     (GodClass, SecuritySmell, DependencyCycle, "Difficulty is too high").
 *     This gate therefore fails on any `error`-level result and ignores the
 *     relative warnings, which is strict without being un-actionable.
 *
 * Usage: php scripts/ci/archeology-gate.php <sarif-path> [repo-root]
 */

$sarifPath = $argv[1] ?? 'build/archeology/sarif/report.sarif.json';
$root = rtrim($argv[2] ?? (string) getcwd(), '/');

$raw = file_get_contents($sarifPath);

if ($raw === false) {
    fwrite(\STDERR, \sprintf("PhpCodeArcheology: SARIF report not found at %s.\n", $sarifPath));

    exit(2);
}

/** @var array<string, mixed> $sarif */
$sarif = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

$prefix = $root . '/';
$errors = 0;
$normalised = 0;

/** @var array<int, array<string, mixed>> $runs */
$runs = \is_array($sarif['runs'] ?? null) ? $sarif['runs'] : [];

foreach ($runs as $runIndex => $run) {
    /** @var array<int, array<string, mixed>> $results */
    $results = \is_array($run['results'] ?? null) ? $run['results'] : [];

    foreach ($results as $resultIndex => $result) {
        if (($result['level'] ?? '') === 'error') {
            ++$errors;
        }

        /** @var array<int, array<string, mixed>> $locations */
        $locations = \is_array($result['locations'] ?? null) ? $result['locations'] : [];

        foreach ($locations as $locationIndex => $location) {
            $artifact = $location['physicalLocation']['artifactLocation'] ?? null;

            if (!\is_array($artifact)) {
                continue;
            }

            $uri = $artifact['uri'] ?? null;

            if (!\is_string($uri)) {
                continue;
            }

            if (str_starts_with($uri, $prefix)) {
                $uri = substr($uri, \strlen($prefix));
            }

            $sarif['runs'][$runIndex]['results'][$resultIndex]['locations'][$locationIndex]['physicalLocation']['artifactLocation']['uri'] = ltrim($uri, '/');
            unset($sarif['runs'][$runIndex]['results'][$resultIndex]['locations'][$locationIndex]['physicalLocation']['artifactLocation']['uriBaseId']);
            ++$normalised;
        }
    }
}

file_put_contents(
    $sarifPath,
    json_encode($sarif, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
);

echo \sprintf("PhpCodeArcheology: normalised %d SARIF location(s).\n", $normalised);

if ($errors > 0) {
    fwrite(\STDERR, \sprintf("PhpCodeArcheology: %d error-level architecture finding(s) \u2014 gate FAILED.\n", $errors));

    exit(1);
}

echo "PhpCodeArcheology: 0 error-level architecture findings \u2014 gate PASSED.\n";
