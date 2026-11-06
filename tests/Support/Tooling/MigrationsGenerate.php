<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use InvalidArgumentException;
use Zef\Framework\Database\MigrationException;
use Zef\Framework\Database\MigrationTemplate;

/**
 * The migrations scaffolding verb's write: the one seam `bin/zef
 * migrations:generate` owns -- a migration directory and a name
 * become ONE new migration file printed from the house template
 * ({@see MigrationTemplate}), no database touched, no runner
 * booted, the template verb's own honesty in the design record
 * (`docs/ci-notes/database-migrations-scaffolding.md`).
 *
 * The refusal table is the contract, each defect named the way the
 * lane's own grammar spells it: a directory that does not exist or
 * is not one refuses exactly as the discovery fold words it
 * ({@see MigrationFiles}'s own message, the read side and the write
 * side answering the same verdict for the same directory); a
 * directory that cannot hold the file refuses naming it; a name
 * colliding with an existing file refuses, never a silent clobber;
 * and a version stamp already held by an existing file refuses at
 * the generate -- the write verb never prints a file the scan would
 * refuse for a duplicate version, an operator scripting the verb
 * learning the verdict from the command that created it, not from
 * the run that would trip over it. The version-prefix guard reads
 * the FILE grammar only (the house convention the template itself
 * prints); a hand-written file whose name carries no stamp keeps
 * its duplicate version for the discovery walk's own refusal,
 * where loading host code belongs.
 *
 * The name and version grammars refuse as construction values
 * ({@see InvalidArgumentException}) before the world is consulted
 * -- the CLI's usage doctrine, a defect in the operator's own
 * argument answered at the boundary.
 */
final readonly class MigrationsGenerate
{
    /**
     * Writes the migration file the name and version stamp spell,
     * and answers the created migration's own identity.
     *
     * @param string $migrationsDirectory the operator's migrations
     *                                    directory (the run verb's own
     *                                    boundary, spelled as the caller
     *                                    typed it)
     * @param string $name                the new migration's name (the
     *                                    template's own grammar)
     * @param string $version             the new migration's 14-digit
     *                                    version stamp (the caller's
     *                                    clock, UTC)
     *
     * @return array{file: non-empty-string, version: non-empty-string,
     *                name: non-empty-string} the created file's path,
     *                its version stamp and its canonical name
     *
     * @throws InvalidArgumentException when the name or the version
     *                                  refuses the template's grammar
     * @throws MigrationException       when the directory refuses, the
     *                                  name collides, the version is
     *                                  already held, or the write fails
     */
    public static function written(string $migrationsDirectory, string $name, string $version): array
    {
        $fileName = MigrationTemplate::fileName($name, $version);

        if (!is_dir($migrationsDirectory)) {
            throw new MigrationException(
                "The migrations directory '{$migrationsDirectory}' does not exist or is not a directory.",
            );
        }

        if (!is_writable($migrationsDirectory)) {
            throw new MigrationException(
                "The migrations directory '{$migrationsDirectory}' is not writable.",
            );
        }

        $directory = mb_rtrim($migrationsDirectory, '/\\');
        $file = $directory . '/' . $fileName;

        if (file_exists($file)) {
            throw new MigrationException("The migration file '{$file}' already exists.");
        }

        $holders = glob($directory . '/' . $version . '-*.php');

        if (\is_array($holders) && $holders !== []) {
            throw new MigrationException(
                "The migration version '{$version}' is already held by the existing"
                . " file '{$holders[0]}'.",
            );
        }

        if (file_put_contents($file, MigrationTemplate::content($name, $version)) === false) {
            throw new MigrationException("The migration file '{$file}' could not be written.");
        }

        // The grammar folds above refused every spelling but the
        // 14-digit stamp, so the answer's own identity is never the
        // empty spelling -- the narrow shape the renderers declare.
        \assert($version !== '');

        return [
            'file' => $file,
            'version' => $version,
            'name' => MigrationTemplate::canonicalName($name),
        ];
    }
}
