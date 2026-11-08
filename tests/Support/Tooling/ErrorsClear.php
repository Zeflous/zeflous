<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use InvalidArgumentException;

/**
 * The `bin/zef errors:clear` verb: the local JSONL error log emptied
 * under an exclusive lock -- the operator's own retention action,
 * lifted out of the CLI's joined branch (#717's structural step).
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0` the
 * log cleared, `1` an operational refusal (the layout refusing the
 * derived address, a missing or unwritable log, a lock the environment
 * refuses), `2` a usage defect (the destructive verb spelled without
 * `--confirm`, an invalid request-id filter, a symlinked log path). The
 * clear truncates under `LOCK_EX` so a concurrently-writing logger never
 * interleaves with the truncation -- the same file the replay and report
 * verbs read. The answer gathers as a {@see CommandOutcome} so the verb
 * stays pure and the dispatch owns the terminals.
 */
final class ErrorsClear
{
    /**
     * Runs the verb over the arguments the operator spelled after it.
     *
     * @param list<string> $arguments the raw arguments after the verb's
     *                                own name: an optional positional log
     *                                path and the required `--confirm`
     *                                marker
     */
    public static function run(array $arguments): CommandOutcome
    {
        try {
            [, $positionalPath] = ErrorsLogArguments::parsed($arguments);
            $path = ErrorsLogPath::resolved($positionalPath);
        } catch (InvalidArgumentException $layoutRefusal) {
            return CommandOutcome::refused(
                1,
                'The error log storage layout refused: ' . $layoutRefusal->getMessage() . "\n",
            );
        } catch (ToolingException $usageDefect) {
            return CommandOutcome::refused(2, $usageDefect->getMessage() . "\n");
        }

        if (!\in_array('--confirm', $arguments, true)) {
            return CommandOutcome::refused(2, "Refusing destructive operation without --confirm.\n");
        }

        if (!is_file($path) || !is_writable($path)) {
            return CommandOutcome::refused(1, "Error log does not exist or is not writable: {$path}\n");
        }

        $stream = fopen($path, 'c+b');
        if ($stream === false || !flock($stream, \LOCK_EX)) {
            if (\is_resource($stream)) {
                fclose($stream);
            }

            return CommandOutcome::refused(1, "Unable to lock error log for clearing.\n");
        }

        ftruncate($stream, 0);
        fflush($stream);
        flock($stream, \LOCK_UN);
        fclose($stream);

        return CommandOutcome::answered("Cleared local JSONL error log: {$path}\n");
    }
}
