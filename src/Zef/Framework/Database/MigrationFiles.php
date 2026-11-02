<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The migration files' discovery fold: the operator's migrations
 * directory as a source of declared migrations -- one `*.php` file,
 * one {@see MigrationInterface} instance, the file's own return the
 * declaration ({@see MigrationFile} the one-file fold).
 *
 * The directory is HOST content (the tree ships no migrations of its
 * own), so the fold owns only the directory's grammar and invents
 * nothing: a directory that does not exist or is not one refuses
 * naming it, and a version declared by two files refuses naming
 * both -- the duplicate guard the runner's own registration grammar
 * ({@see MigrationRegistration}) would otherwise fire without the
 * file names an operator needs. The declaration's OWN corridor (the
 * 14-digit version stamp, the name's byte ceiling) stays the
 * registration grammar's refusal at the register verb, exactly where
 * the orchestration slice pinned it: the fold owns the directory's
 * shape, never the migration's.
 *
 * The scan is flat and alphabetical: every `*.php` in the directory
 * (the glob skips dot-files, so a staging editor's `.swp.php`-shaped
 * strays stay invisible the way the delivery staging doctrine spells)
 * -- no recursion, no ordering contract beyond the walk's own
 * determinism, because the runner sorts by VERSION, never by file
 * name.
 *
 * @internal
 */
final class MigrationFiles
{
    /**
     * Discovers the declared migrations of one migrations directory.
     *
     * @return array<string, MigrationInterface> the migrations keyed
     *                                           by their version stamps
     *
     * @throws MigrationException when the directory does not exist,
     *                            a file refuses ({@see MigrationFile}),
     *                            or two files declare the same version
     */
    public static function discover(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new MigrationException(
                "The migrations directory '{$directory}' does not exist or is not a directory.",
            );
        }

        $migrations = [];
        $declaredBy = [];

        $files = glob(mb_rtrim($directory, '/\\') . '/*.php');

        foreach (\is_array($files) ? $files : [] as $file) {
            $migration = MigrationFile::declaration($file);
            $version = $migration->version();

            if (isset($migrations[$version])) {
                throw new MigrationException(
                    "Migration version '{$version}' is declared by both"
                    . " '{$declaredBy[$version]}' and '{$file}'.",
                );
            }

            $migrations[$version] = $migration;
            $declaredBy[$version] = $file;
        }

        return $migrations;
    }
}
