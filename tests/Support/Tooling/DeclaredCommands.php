<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Throwable;
use Zef\Framework\Console\ConsoleCommandEntry;
use Zef\Framework\Console\FrameworkVerbs;
use Zef\Framework\ConsoleWiring;

/**
 * The `bin/zef <command> <config-directory> [args...]` declared-command
 * dispatch: the host's and the active modules' own verbs' whole answer,
 * lifted out of the CLI's fall-through block (#717's structural step,
 * the declared-command dispatch's slice).
 *
 * The dispatch owns the seam's own judgement order, exactly the order
 * the joined block spelled. First the exclusion: a verb the framework's
 * own vocabulary carries is NEVER the registry's to answer -- the CLI's
 * own branches own the framework's verbs (the positional-path commands
 * among them, which this seam must not intercept), and the registry
 * itself refuses a declaration that collides with one -- so a framework
 * verb answers `null` and the seam stays silent, letting the branches
 * below it speak. Then the configuration directory: resolved and judged
 * a real directory, a missing or file-shaped spelling the usage defect
 * (exit 2). Then the one-call boot over the resolved directory and the
 * joined `console.commands` table's own lookup: an operator's verb no
 * declaration carries the unknown-verb refusal (exit 2) with the way
 * out the directory spells. Then the handler: resolved through the
 * booted container fail-closed, and run with the arguments after the
 * configuration directory, its own exit code passed through verbatim
 * (exit 0, 1, 2 -- the handler's vocabulary, not the dispatch's). A
 * boot or resolution that refuses answers the dispatch's own refusal
 * word with the failing unit's chain (exit 1).
 *
 * The handler owns its own terminals: a declared handler writes its
 * answer and its refusals to the process's STDOUT and STDERR itself
 * during its run, so the answered outcome carries the handler's exit
 * code alone -- the verb's own refusals carry their bytes, the
 * handler's answer carries only its code. The answer gathers as a
 * {@see CommandOutcome} so the verb stays pure, unit-testable and
 * mutation-honest, the null exclusion included.
 */
final class DeclaredCommands
{
    /**
     * Runs the dispatch over the operator's verb and the arguments that
     * followed it.
     *
     * @param non-empty-string $command   the operator's verb -- a framework
     *                                    verb of {@see FrameworkVerbs} answers null (the CLI's own
     *                                    branch owns it), any other spelling the registry's own
     *                                    lookup
     * @param list<string>     $arguments the raw arguments after the command's
     *                                    own name: the configuration directory positional first,
     *                                    then the handler's own arguments verbatim
     *
     * @return null|CommandOutcome the dispatch's whole answer, or null when
     *                             the verb is the CLI's own and the seam must stay silent
     */
    public static function run(string $command, array $arguments): ?CommandOutcome
    {
        if (isset(FrameworkVerbs::all()[$command])) {
            return null;
        }

        $configDirectoryArgument = $arguments[0] ?? '';

        // The arm guarantees the positional's presence; the empty
        // spelling still answers deterministically here -- realpath('')
        // resolves the process's own working directory, a directory the
        // operator never spelled.
        if ($configDirectoryArgument === '') {
            return CommandOutcome::refused(
                2,
                "Configuration directory does not exist: \n",
            );
        }

        $resolvedConfigDirectory = realpath($configDirectoryArgument);
        if ($resolvedConfigDirectory === false || !is_dir($resolvedConfigDirectory)) {
            return CommandOutcome::refused(
                2,
                "Configuration directory does not exist: {$configDirectoryArgument}\n",
            );
        }

        try {
            $kernel = CliInspectionBoot::kernel($resolvedConfigDirectory);
            $commands = ConsoleWiring::commands($kernel->config());

            $entry = $commands->named($command);

            if (!$entry instanceof ConsoleCommandEntry) {
                return CommandOutcome::refused(
                    2,
                    "Unknown command '{$command}'. Run 'bin/zef list <config-directory>'"
                    . " to see the declared commands.\n",
                );
            }

            $handler = ConsoleWiring::handler($kernel->container(), $entry);

            // The handler owns its own terminals; the outcome carries its
            // exit code alone, the code the arm exits with verbatim.
            return new CommandOutcome($handler->run(\array_slice($arguments, 1)));
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(
                1,
                'The command dispatch refused: ' . $throwable->getMessage() . "\n",
            );
        }
    }
}
