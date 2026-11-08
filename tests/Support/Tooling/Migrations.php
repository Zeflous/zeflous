<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

/**
 * The `bin/zef migrations:*` verb: the migrations command family's
 * whole answer, lifted out of the CLI's joined branch (#717's
 * structural step, the migrations family's slice).
 *
 * The verb owns the family's joined argument grammar and its
 * refusals: the positional configuration directory every spelling
 * carries; the optional migrations-directory positional (defaulting
 * to the configuration directory's sibling `migrations`, the
 * directory the whole storage lane's doctrine answers); the
 * `migrations:generate` spelling's own second positional (the
 * migration's name, with one or two positionals the accepted shapes
 * and every other count a usage defect); the `--steps=<n>` flag that
 * belongs to `migrations:rollback` alone (judged by the one-to-nine
 * digits grammar, the last spelling winning); and the `--json` flag
 * every spelling answers. The answers themselves are the family's
 * own units -- {@see MigrationsGenerate::written()} for the
 * scaffolding verb (its template-grammar refusal answering the usage
 * with exit 2, its operational refusals the command's own refusal
 * word with exit 1), {@see MigrationsBoot::booted()} for the three
 * inspection and mutation verbs with their six renderings -- the
 * verb never re-derives what the units answer.
 *
 * The grammar is the exit-code vocabulary the whole CLI answers: `0`
 * the answer, `1` an operational refusal (the boot refusing the
 * configuration, the store refusing a migration), `2` a usage defect
 * (the missing or flag-shaped positional, the unresolved directory,
 * the unknown or mis-scoped flag, the invalid step count, the
 * template grammar refusing a name). The answer gathers as a
 * {@see CommandOutcome} so the verb stays pure and the dispatch owns
 * the terminals.
 */
final class Migrations
{
    /**
     * Runs the verb over the command spelling and the arguments the
     * operator spelled after it.
     *
     * @param non-empty-string $command   the family's own command name
     *                                    (`migrations:status`,
     *                                    `migrations:run`,
     *                                    `migrations:rollback` or
     *                                    `migrations:generate`) -- the
     *                                    grammar's shape selector, the
     *                                    same branch condition the joined
     *                                    branch read
     * @param list<string>     $arguments the raw arguments after the
     *                                    command's own name: the
     *                                    configuration directory
     *                                    positional, the optional
     *                                    migrations-directory positional
     *                                    (and the generate name), and
     *                                    the family's flags
     */
    public static function run(string $command, array $arguments): CommandOutcome
    {
        $migrationsUsage = 'Usage: bin/zef migrations:status <config-directory>'
            . " [<migrations-directory>] [--json]\n"
            . '       bin/zef migrations:run <config-directory>'
            . " [<migrations-directory>] [--json]\n"
            . '       bin/zef migrations:rollback <config-directory>'
            . " [<migrations-directory>] [--steps=<n>] [--json]\n"
            . '       bin/zef migrations:generate <config-directory>'
            . " [<migrations-directory>] <name> [--json]\n"
            . '       (the migrations directory defaults to the configuration'
            . " directory's sibling 'migrations')\n";

        $configDirectoryArgument = $arguments[0] ?? '';
        $migrationsDirectoryArgument = null;
        $generateNameArgument = null;
        $answerAsJson = false;
        $rollbackSteps = 1;

        if ($configDirectoryArgument === '' || str_starts_with($configDirectoryArgument, '--')) {
            return CommandOutcome::refused(2, $migrationsUsage);
        }

        $positionalArguments = [];

        foreach (\array_slice($arguments, 1) as $argument) {
            if ($argument === '--json') {
                $answerAsJson = true;
            } elseif (str_starts_with($argument, '--steps=') && $command === 'migrations:rollback') {
                $steps = mb_substr($argument, mb_strlen('--steps='));
                if (preg_match('/^[1-9][0-9]{0,8}$/', $steps) !== 1) {
                    return CommandOutcome::refused(2, $migrationsUsage);
                }

                $rollbackSteps = (int) $steps;
            } elseif (str_starts_with($argument, '--')) {
                return CommandOutcome::refused(2, $migrationsUsage);
            } else {
                $positionalArguments[] = $argument;
            }
        }

        if ($command === 'migrations:generate') {
            if (\count($positionalArguments) === 2) {
                [$migrationsDirectoryArgument, $generateNameArgument] = $positionalArguments;
            } elseif (\count($positionalArguments) === 1) {
                $generateNameArgument = $positionalArguments[0];
            } else {
                return CommandOutcome::refused(2, $migrationsUsage);
            }
        } elseif ($positionalArguments !== []) {
            if (\count($positionalArguments) > 1) {
                return CommandOutcome::refused(2, $migrationsUsage);
            }

            $migrationsDirectoryArgument = $positionalArguments[0];
        }

        $resolvedConfigDirectory = realpath($configDirectoryArgument);
        if ($resolvedConfigDirectory === false || !is_dir($resolvedConfigDirectory)) {
            return CommandOutcome::refused(
                2,
                "Configuration directory does not exist: {$configDirectoryArgument}\n",
            );
        }

        $migrationsDirectory = $migrationsDirectoryArgument
            ?? \dirname($resolvedConfigDirectory) . '/migrations';

        if ($command === 'migrations:generate') {
            // The count grammar above guaranteed the name's presence
            // for every generate spelling that reached this render.
            \assert($generateNameArgument !== null);

            $generateVersionStamp = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('YmdHis');

            try {
                $generated = MigrationsGenerate::written(
                    $migrationsDirectory,
                    $generateNameArgument,
                    $generateVersionStamp,
                );

                return CommandOutcome::answered(
                    $answerAsJson
                        ? MigrationsGenerateJson::rendered(
                            $generated['version'],
                            $generated['name'],
                            $generated['file'],
                        ) . "\n"
                        : MigrationsGenerateText::created($generated['file']),
                );
            } catch (InvalidArgumentException) {
                return CommandOutcome::refused(2, $migrationsUsage);
            } catch (Throwable $generateFailure) {
                return CommandOutcome::refused(
                    1,
                    'The migrations command refused: ' . $generateFailure->getMessage() . "\n",
                );
            }
        }

        try {
            $migrationsBoot = MigrationsBoot::booted(
                $resolvedConfigDirectory,
                $migrationsDirectory,
            );

            if ($command === 'migrations:status') {
                return CommandOutcome::answered(
                    $answerAsJson
                        ? MigrationsStatusJson::rendered(
                            $migrationsBoot->names(),
                            $migrationsBoot->applied(),
                            $migrationsBoot->plan(),
                        ) . "\n"
                        : MigrationsStatusText::table(
                            $migrationsBoot->names(),
                            $migrationsBoot->applied(),
                            $migrationsBoot->plan(),
                        ),
                );
            }

            if ($command === 'migrations:run') {
                return CommandOutcome::answered(
                    $answerAsJson
                        ? MigrationsRunJson::applied(
                            $migrationsBoot->names(),
                            $migrationsBoot->migrate(),
                        ) . "\n"
                        : MigrationsRunText::applied(
                            $migrationsBoot->names(),
                            $migrationsBoot->migrate(),
                        ),
                );
            }

            return CommandOutcome::answered(
                $answerAsJson
                    ? MigrationsRollbackJson::rolledBack(
                        $migrationsBoot->names(),
                        $migrationsBoot->rollback($rollbackSteps),
                    ) . "\n"
                    : MigrationsRunText::rolledBack(
                        $migrationsBoot->names(),
                        $migrationsBoot->rollback($rollbackSteps),
                    ),
            );
        } catch (Throwable $throwable) {
            return CommandOutcome::refused(
                1,
                'The migrations command refused: ' . $throwable->getMessage() . "\n",
            );
        }
    }
}
