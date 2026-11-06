<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The migrations generate verb's answer text: the one shape `bin/zef
 * migrations:generate` renders -- the family's own header line
 * (verb, the count with its honest shape: the verb writes exactly
 * one file, by construction) and the created file's path on its own
 * indented row, the design record's own "the path printed". The
 * path is the answer an operator scripts against; the version and
 * the name ride the file's own name, spelled inside the path.
 */
final readonly class MigrationsGenerateText
{
    /**
     * The created migration file's answer.
     *
     * @param non-empty-string $file the created migration file's path
     */
    public static function created(string $file): string
    {
        return "Migrations generate -- 1 file created:\n  {$file}\n";
    }
}
