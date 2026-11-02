<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The migrations status table: the plain answer `bin/zef
 * migrations:status` renders -- the counts on the header line
 * (applied, pending, declared -- the three numbers an operator
 * reconciles), then the applied section and the pending section in
 * the runner's own order, each version carrying the declaration's
 * name, or the honest `(undeclared)` spelling for an applied version
 * whose declaration has since left the directory -- a bookkeeping
 * row without a file is a fact the operator needs to SEE, not a
 * rendering that hides it.
 *
 * The sections are spelled even when empty: an honest empty (a
 * fresh database, a fully-migrated one) is a shape a script can
 * diff, never a missing section that reads as a failure.
 */
final readonly class MigrationsStatusText
{
    /**
     * The whole status, one line per version.
     *
     * @param array<string, string> $names   the declared migrations' names keyed
     *                                       by version stamp
     * @param list<string>          $applied versions recorded as applied, ascending
     * @param list<string>          $pending versions migrate() would apply right now
     */
    public static function table(array $names, array $applied, array $pending): string
    {
        $lines = [
            \sprintf(
                'Migrations status -- %d applied, %d pending, %d declared',
                \count($applied),
                \count($pending),
                \count($names),
            ),
            'applied:',
        ];

        foreach ($applied as $version) {
            $lines[] = '  ' . $version . '  ' . ($names[$version] ?? '(undeclared)');
        }

        $lines[] = 'pending:';

        foreach ($pending as $version) {
            $lines[] = '  ' . $version . '  ' . ($names[$version] ?? '(undeclared)');
        }

        return implode("\n", $lines) . "\n";
    }
}
