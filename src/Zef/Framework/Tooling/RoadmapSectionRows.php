<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Counts the data rows of each numbered section of a roadmap document.
 *
 * A section's table header and its separator row are not data rows, so a
 * section whose table has only a header counts as zero. The count is the
 * derived value the `Summary` table must agree with
 * ({@see RoadmapCountRules}) and the evidence the contradiction rule uses
 * ({@see RoadmapContradictionRules}).
 */
final class RoadmapSectionRows
{
    /**
     * The number of data rows in each numbered section, keyed by the normalised
     * section title.
     *
     * @param list<string> $lines
     *
     * @return array<string, int>
     */
    public static function counts(array $lines): array
    {
        $counts = [];
        $current = null;
        $inTable = false;

        foreach ($lines as $line) {
            if (preg_match('/^## \d+\.\s+(.+)$/', $line, $matches) === 1) {
                $current = RoadmapTableCells::normalise($matches[1]);
                $counts[$current] = 0;
                $inTable = false;

                continue;
            }

            if (str_starts_with($line, '## ')) {
                $current = null;
                $inTable = false;

                continue;
            }

            if ($current === null || !str_starts_with($line, '|')) {
                $inTable = false;

                continue;
            }

            $cells = RoadmapTableCells::cells($line);

            if (RoadmapTableCells::isSeparator($cells)) {
                continue;
            }

            if (!$inTable) {
                // The first row of a table is its header; it is not a data row.
                $inTable = true;

                continue;
            }

            ++$counts[$current];
        }

        return $counts;
    }
}
