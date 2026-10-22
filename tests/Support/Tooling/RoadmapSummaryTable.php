<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Reads the derived `Summary` table of a roadmap document.
 *
 * The `Summary` table is a two-column `Area | Count` view; a row whose second
 * cell does not start with an integer is not a count row and is skipped. The
 * table is the derived value {@see RoadmapCountRules} checks against the
 * sections it summarises.
 */
final class RoadmapSummaryTable
{
    /**
     * The heading that introduces the derived summary table.
     */
    private const string SUMMARY_HEADING = '## Summary';

    /**
     * The `Summary` table as a map of label => integer count.
     *
     * @param list<string> $lines
     *
     * @return array<string, int>
     */
    public static function counts(array $lines): array
    {
        $counts = [];
        $inSummary = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '## ')) {
                $inSummary = mb_trim($line) === self::SUMMARY_HEADING;

                continue;
            }

            if (!$inSummary || !str_starts_with($line, '|')) {
                continue;
            }

            $cells = RoadmapTableCells::cells($line);

            if (\count($cells) !== 2) {
                continue;
            }

            $value = self::leadingInteger($cells[1]);

            if ($value === null) {
                continue;
            }

            $counts[$cells[0]] = $value;
        }

        return $counts;
    }

    /**
     * The integer a cell starts with, or null when it does not start with one.
     */
    private static function leadingInteger(string $cell): ?int
    {
        if (preg_match('/^\d+/', mb_trim($cell, "`* \t"), $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }
}
