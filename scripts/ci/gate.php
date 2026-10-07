<?php

declare(strict_types=1);

/**
 * Thin CLI entry point for the ZEF tooling gates.
 *
 * All gate logic lives in `Zef\Framework\Tooling\*` (unit-tested, fully covered);
 * this file only wires the real process runner and maps the result to an exit
 * code, so it stays a few lines long.
 *
 * Usage: php scripts/ci/gate.php <coverage|mutation|baseline|audit|lint|smoke> [arg]
 */

use Zef\Framework\Tooling\GateRunner;
use Zef\Framework\Tooling\ToolingException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);

$runner = new GateRunner($root, static function (string $command): array {
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return [$exitCode, implode(PHP_EOL, $output)];
});

try {
    $result = $runner->run($argv[1] ?? '', array_slice($argv, 2));
} catch (ToolingException $toolingException) {
    fwrite(STDERR, $toolingException->getMessage() . PHP_EOL);
    exit(1);
}

$stream = $result->passed ? STDOUT : STDERR;
fwrite($stream, $result->message . PHP_EOL);
exit($result->exitCode());
