<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The commit-cell rule of the roadmap document gate.
 *
 * It lives in its own class for the same reason {@see RoadmapTableRules} does:
 * the repository's architecture gate (`php-codearch-config.yaml`,
 * `tooComplex.difficulty: 20`) scores a class that carries several operator
 * varieties above the ceiling, so each file owns one focused review surface.
 *
 * The rule closes the class of defect that shipped a literal `PENDING`
 * placeholder in the `Commit` column of `ROADMAP-HISTORY.md`: the structural
 * rules only check that a row is closed and that the columns line up, so a
 * semantically empty cell passed CI. Every cell under a `Commit` column must
 * be a real abbreviated merge SHA.
 */
final class RoadmapCommitRules
{
    /**
     * Invariant D: in any table that has a `Commit` column, every data cell in
     * that column must be a hexadecimal merge SHA (7..40 chars, optionally
     * wrapped in backticks). A placeholder such as `PENDING` is a failure.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function invalidCommitCells(string $name, array $lines): array
    {
        $failures = [];
        $commitColumn = null;

        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, '|')) {
                $commitColumn = null;

                continue;
            }

            $cells = self::cells($line);

            if ($commitColumn === null) {
                $commitColumn = self::commitColumn($cells) ?? -1;

                continue;
            }

            if ($commitColumn === -1 || self::isSeparator($cells)) {
                continue;
            }

            $cell = $cells[$commitColumn] ?? '';

            if (preg_match('/^[0-9a-f]{7,40}$/', mb_trim($cell, "` \t")) === 1) {
                continue;
            }

            $failures[] = \sprintf(
                '%s:%d has a Commit cell that is not a merge SHA ("%s").',
                $name,
                $index + 1,
                $cell,
            );
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    private static function cells(string $line): array
    {
        $trimmed = mb_trim($line, "| \t");

        return array_map(mb_trim(...), explode('|', $trimmed));
    }

    /**
     * @param list<string> $cells
     */
    private static function commitColumn(array $cells): ?int
    {
        foreach ($cells as $position => $cell) {
            if (strcasecmp($cell, 'commit') === 0) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param list<string> $cells
     */
    private static function isSeparator(array $cells): bool
    {
        return array_all($cells, static fn (string $cell): bool => preg_match('/^:?-{3,}:?$/', $cell) === 1);
    }
}
