<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Throwable;
use Zef\Framework\ConsoleWiring;

/**
 * The `bin/zef list` verb: the directory listing command's whole
 * answer, lifted out of the CLI's joined branch (#717's structural
 * step, the list family's slice).
 *
 * The verb owns the command's argument grammar and its refusals: the
 * positional configuration directory, the `--json` flag choosing the
 * machine-readable rendering over the table, the usage refusal when
 * the positional is missing or flag-shaped, and the path-defect
 * refusal when the directory does not resolve. The answer itself is
 * the inspection boot's own listing ({@see CliInspectionBoot::kernel()}
 * booted through the same orchestrator the runtime rides, {@see
 * ConsoleWiring::commands()} reading the declared map) rendered by
 * the two presentation units ({@see CliListText} the table, {@see
 * CliListJson} the JSON form) -- the verb never re-derives what the
 * boot answers, and a refused inspection surfaces with the boot's
 * own chain, exactly the words the joined branch spelled.
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0`
 * the listing answered, `1` an operational refusal (the inspection
 * refusing the configuration), `2` a usage defect (the missing
 * positional or the unresolved directory). The answer gathers as a
 * {@see CommandOutcome} so the verb stays pure and the dispatch owns
 * the terminals.
 */
final class CliList
{
    /**
     * Runs the verb over the arguments the operator spelled after it.
     *
     * @param list<string> $arguments the raw arguments after the verb's
     *                                own name: the configuration
     *                                directory positional and the
     *                                optional `--json` flag
     */
    public static function run(array $arguments): CommandOutcome
    {
        $configDirectory = $arguments[0] ?? '';
        $listAsJson = \in_array('--json', \array_slice($arguments, 1), true);

        if ($configDirectory === '' || str_starts_with($configDirectory, '--')) {
            return CommandOutcome::refused(2, "Usage: bin/zef list <config-directory> [--json]\n");
        }

        $resolved = realpath($configDirectory);
        if ($resolved === false || !is_dir($resolved)) {
            return CommandOutcome::refused(2, "Configuration directory does not exist: {$configDirectory}\n");
        }

        try {
            $kernel = CliInspectionBoot::kernel($resolved);
            $commands = ConsoleWiring::commands($kernel->config());

            return CommandOutcome::answered(
                $listAsJson
                    ? CliListJson::rendered($commands) . "\n"
                    : CliListText::table($commands),
            );
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(1, 'The CLI inspection refused: ' . $throwable->getMessage() . "\n");
        }
    }
}
