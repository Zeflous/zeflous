<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Counts the data rows of each numbered section of a roadmap document.
 *
 * A section's table header is not a data row, so a section whose table has only
 * a header counts as zero. The count is the derived value the `Summary` table
 * must agree with ({@see RoadmapCountRules}) and the evidence the contradiction
 * rule uses ({@see RoadmapContradictionRules}).
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
        $headerSeen = false;

        foreach ($lines as $line) {
            if (preg_match('/^## \d+\.\s+(.+)/', $line, $matches) === 1) {
                $current = RoadmapTableCells::normalise($matches[1]);
                $counts[$current] = 0;
                $headerSeen = false;

                continue;
            }

            if (str_starts_with($line, '## ')) {
                $current = null;

                continue;
            }

            if ($current === null) {
                $headerSeen = false;

                continue;
            }

            if (!str_starts_with($line, '|')) {
                $headerSeen = false;

                continue;
            }

            if (RoadmapTableCells::isSeparator(RoadmapTableCells::cells($line))) {
                continue;
            }

            if (!$headerSeen) {
                // The first row of a table is its header; it is not a data row.
                $headerSeen = true;

                continue;
            }

            ++$counts[$current];
        }

        return $counts;
    }
}
