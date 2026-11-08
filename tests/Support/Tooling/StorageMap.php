<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Throwable;
use Zef\Framework\Storage\StorageLayout;

/**
 * The `bin/zef storage:map` verb: the storage layout's lane map
 * command's whole answer, lifted out of the CLI's joined branch
 * (#717's structural step, the storage family's slice).
 *
 * The verb owns the command's argument grammar and its refusals: the
 * optional positional storage root (the one-way precedence the whole
 * storage lane answers -- the positional, then the `ZEF_STORAGE_ROOT`
 * environment spelling, then the repository's own `files` skeleton),
 * the `--json` flag choosing the machine-readable rendering over the
 * table, and the usage refusal (exit 2) for an unknown flag or a
 * second positional. The answer itself is the layout's own walk
 * ({@see StorageLayout::resolve()} fail-closed over the root) rendered
 * by the two presentation units ({@see StorageMapText} the table,
 * {@see StorageMapJson} the JSON form) -- the verb never re-derives
 * what the layout answers, and a refused layout surfaces with the
 * layout's own defect, exactly the words the joined branch spelled.
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0`
 * the map answered, `1` an operational refusal (the layout refusing
 * the root), `2` a usage defect (the unknown flag, the second
 * positional). The answer gathers as a {@see CommandOutcome} so the
 * verb stays pure and the dispatch owns the terminals.
 */
final class StorageMap
{
    /**
     * Runs the verb over the arguments the operator spelled after it.
     *
     * @param list<string> $arguments the raw arguments after the verb's
     *                                own name: an optional storage-root
     *                                positional and the `--json` flag
     */
    public static function run(array $arguments): CommandOutcome
    {
        $storageRootArgument = null;
        $storageAsJson = false;

        foreach ($arguments as $argument) {
            if ($argument === '--json') {
                $storageAsJson = true;
            } elseif (str_starts_with($argument, '--')) {
                return CommandOutcome::refused(2, "Usage: bin/zef storage:map [<storage-root>] [--json]\n");
            } elseif ($storageRootArgument !== null) {
                return CommandOutcome::refused(2, "Usage: bin/zef storage:map [<storage-root>] [--json]\n");
            } else {
                $storageRootArgument = $argument;
            }
        }

        $environmentRoot = getenv('ZEF_STORAGE_ROOT');
        $storageRoot = $storageRootArgument ?? (
            \is_string($environmentRoot) && $environmentRoot !== ''
                ? $environmentRoot
                : \dirname(__DIR__, 3) . '/files'
        );

        try {
            $storagePaths = StorageLayout::resolve($storageRoot);

            return CommandOutcome::answered(
                $storageAsJson
                    ? StorageMapJson::rendered($storagePaths) . "\n"
                    : StorageMapText::table($storagePaths),
            );
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(1, 'The storage layout refused: ' . $throwable->getMessage() . "\n");
        }
    }
}
