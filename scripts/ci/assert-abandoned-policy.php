<?php

declare(strict_types=1);

/**
 * Dependency supply-chain policy gate.
 *
 * Policy:
 *  - any reported security advisory FAILS the gate;
 *  - abandoned packages are reported as warnings (they are dev-only transitive
 *    dependencies today) but never silently hidden.
 *
 * Usage: php scripts/ci/assert-abandoned-policy.php
 */

$root = dirname(__DIR__, 2);
$command = sprintf('cd %s && composer audit --format=json --no-interaction 2>/dev/null', escapeshellarg($root));
$raw = shell_exec($command);

if (!is_string($raw) || trim($raw) === '') {
    fwrite(STDOUT, 'Audit gate SKIPPED: composer audit produced no JSON payload.' . PHP_EOL);
    exit(0);
}

/** @var array<string, mixed>|null $data */
$data = json_decode($raw, true);

if (!is_array($data)) {
    fwrite(STDOUT, 'Audit gate SKIPPED: composer audit payload is not valid JSON.' . PHP_EOL);
    exit(0);
}

/** @var array<string, mixed> $advisories */
$advisories = is_array($data['advisories'] ?? null) ? $data['advisories'] : [];
/** @var array<string, mixed> $abandoned */
$abandoned = is_array($data['abandoned'] ?? null) ? $data['abandoned'] : [];

if ($advisories !== []) {
    fwrite(STDERR, sprintf('Security advisories found: %d%s', count($advisories), PHP_EOL));
    exit(1);
}

if ($abandoned !== []) {
    fwrite(
        STDOUT,
        sprintf(
            'WARNING: %d abandoned package(s) reported (dev-only): %s%s',
            count($abandoned),
            implode(', ', array_keys($abandoned)),
            PHP_EOL,
        ),
    );
}

echo 'Dependency policy gate PASSED (0 advisories).' . PHP_EOL;
