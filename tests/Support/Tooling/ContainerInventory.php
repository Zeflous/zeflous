<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Throwable;

/**
 * The `bin/zef container:inventory` verb: the container analysis
 * command's whole answer, lifted out of the CLI's joined branch
 * (#717's structural step, the container family's slice).
 *
 * The verb owns the command's argument grammar and its refusals: the
 * positional configuration directory, the optional `--id=<service>`
 * filter (an empty `--id=` spelling answers the whole table -- the
 * same null the absent flag answers, so the report's two forms stay
 * reachable from one grammar), the usage refusal when the positional
 * is missing or flag-shaped, and the path-defect refusal when the
 * directory does not resolve. The answer itself is the boot's own
 * report ({@see ContainerInventoryReport::rendering()}): the verb
 * never re-derives what the boot answers -- a refused boot surfaces
 * as the operational refusal with the boot's own chain, exactly the
 * words the joined branch spelled.
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0`
 * the inventory answered, `1` an operational refusal (the boot
 * refusing the configuration), `2` a usage defect (the missing
 * positional or the unresolved directory). The answer gathers as a
 * {@see CommandOutcome} so the verb stays pure and the dispatch owns
 * the terminals.
 */
final class ContainerInventory
{
    /**
     * Runs the verb over the arguments the operator spelled after it.
     *
     * @param list<string> $arguments the raw arguments after the verb's
     *                                own name: the configuration
     *                                directory positional and the
     *                                optional `--id=<service>` filter
     */
    public static function run(array $arguments): CommandOutcome
    {
        $configDirectory = $arguments[0] ?? '';
        $identifier = null;

        foreach (\array_slice($arguments, 1) as $argument) {
            if (!str_starts_with($argument, '--id=')) {
                continue;
            }

            $identifier = mb_substr($argument, mb_strlen('--id='));
        }

        if ($configDirectory === '' || str_starts_with($configDirectory, '--')) {
            return CommandOutcome::refused(
                2,
                "Usage: bin/zef container:inventory <config-directory> [--id=<service>]\n",
            );
        }

        $resolved = realpath($configDirectory);
        if ($resolved === false || !is_dir($resolved)) {
            return CommandOutcome::refused(2, "Configuration directory does not exist: {$configDirectory}\n");
        }

        try {
            return CommandOutcome::answered(ContainerInventoryReport::rendering(
                $resolved,
                $identifier === '' ? null : $identifier,
            ));
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(1, 'The container boot refused: ' . $throwable->getMessage() . "\n");
        }
    }
}
