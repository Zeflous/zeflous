<?php

declare(strict_types=1);

/**
 * Thin CLI entry point for the ZEF tooling gates.
 *
 * All gate logic lives in `Zef\Framework\Tooling\*` (unit-tested, fully covered);
 * this file only wires the real process runner and maps the result to an exit
 * code, so it stays a few lines long.
 *
 * Usage: php scripts/ci/gate.php <coverage|zero-deps|mutation|baseline|audit|lint|workflow-concurrency|smoke> [arg]
 */

use Zef\Framework\Tooling\GateRunner;
use Zef\Framework\Tooling\ToolingException;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);

$runner = new GateRunner($root, static function (string $command): array {
    // The gate classes build their commands as shell strings (they call
    // escapeshellarg() on every argument), so the runner must keep shell
    // semantics. Symfony's Process is used with `fromShellCommandline()` --
    // NOT with an argument array: passing the shell string through
    // `explode(' ', ...)` would hand the literal quotes produced by
    // escapeshellarg() to execve(), which then fails with exit 127
    // ("'/usr/bin/php8.4': not found") and makes every file look like a
    // syntax error. `fromShellCommandline()` runs the string through the
    // shell exactly like the previous exec() did, while still giving us the
    // exit code and the combined output.
    $process = Process::fromShellCommandline($command);
    $process->run();

    return [$process->getExitCode() ?? 1, $process->getOutput() . $process->getErrorOutput()];
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
