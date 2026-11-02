<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The one-file declaration fold: what a single migration file declares.
 * Including the file executes host code; that is what a migration file
 * IS (the reference's own register verb hands over instances the host
 * composed by hand -- the directory only spells where the operator's
 * runner finds them without a bootstrap of their own). The fold owns
 * the file's OWN grammar and invents nothing: a glob match that is not
 * a regular file (a directory wearing a `*.php` name) refuses naming
 * the entry, and a file whose return is not a migration instance
 * refuses naming the file -- the host's own throw inside the file
 * surfacing unchanged, the way every host-code seam behaves.
 *
 * @internal
 */
final class MigrationFile
{
    /**
     * Answers the migration one migration file declares.
     *
     * @param string $file the file path the walk reached (the regular
     *                     file guard below the authority, so the empty
     *                     spelling refuses like any other non-file)
     *
     * @throws MigrationException when the entry is not a regular file
     *                            or the file's return is not a
     *                            MigrationInterface instance
     */
    public static function declaration(string $file): MigrationInterface
    {
        if (!is_file($file)) {
            throw new MigrationException(
                "Migration file '{$file}' is not a regular file.",
            );
        }

        $migration = include $file;

        if (!$migration instanceof MigrationInterface) {
            throw new MigrationException(
                "Migration file '{$file}' must return a MigrationInterface instance.",
            );
        }

        return $migration;
    }
}
