<?php

declare(strict_types=1);

/**
 * Strict dependency supply-chain gate.
 *
 * Policy (fail-closed):
 *  - any security advisory FAILS the gate unless the package is explicitly
 *    allow-listed in `composer.json` -> `config.audit.ignore`;
 *  - any abandoned package FAILS the gate unless it is allow-listed in the same
 *    list, so that an abandoned dependency can never slip in unnoticed.
 *
 * Usage: php scripts/ci/audit-strict.php
 */

$root = dirname(__DIR__, 2);

$composerJsonRaw = file_get_contents($root . '/composer.json');
$composerJson = is_string($composerJsonRaw) ? json_decode($composerJsonRaw, true) : null;

$allowlist = [];

if (is_array($composerJson)) {
    $configured = $composerJson['config']['audit']['ignore'] ?? null;

    if (is_array($configured)) {
        foreach ($configured as $entry) {
            if (is_string($entry)) {
                $allowlist[] = $entry;
            }
        }
    }
}

$command = sprintf('cd %s && composer audit --format=json --locked --no-interaction 2>/dev/null', escapeshellarg($root));
$raw = shell_exec($command);

if (!is_string($raw) || trim($raw) === '') {
    fwrite(STDERR, 'Strict audit FAILED: composer audit produced no JSON payload.' . PHP_EOL);
    exit(1);
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    fwrite(STDERR, 'Strict audit FAILED: composer audit payload is not valid JSON.' . PHP_EOL);
    exit(1);
}

$advisories = is_array($data['advisories'] ?? null) ? $data['advisories'] : [];
$abandoned = is_array($data['abandoned'] ?? null) ? $data['abandoned'] : [];

$blockedAdvisories = array_values(array_diff(array_keys($advisories), $allowlist));
$blockedAbandoned = array_values(array_diff(array_keys($abandoned), $allowlist));

if ($blockedAdvisories !== []) {
    fwrite(
        STDERR,
        sprintf(
            'Strict audit FAILED: %d package(s) with security advisories: %s%s',
            count($blockedAdvisories),
            implode(', ', $blockedAdvisories),
            PHP_EOL,
        ),
    );
    exit(1);
}

if ($blockedAbandoned !== []) {
    fwrite(
        STDERR,
        sprintf(
            'Strict audit FAILED: %d abandoned package(s) outside the allow-list: %s%s',
            count($blockedAbandoned),
            implode(', ', $blockedAbandoned),
            PHP_EOL,
        ),
    );
    exit(1);
}

if ($allowlist !== []) {
    fwrite(STDOUT, sprintf('Allow-listed (documented exceptions): %s%s', implode(', ', $allowlist), PHP_EOL));
}

echo 'Strict audit PASSED: 0 security advisories, 0 unallow-listed abandoned packages.' . PHP_EOL;
