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
 *
 * The header is recognised structurally: in a Markdown table the header row is
 * the one immediately before the `---` separator, so a row is a data row only
 * once a separator has been seen. Tracking that fact (rather than a "first row"
 * flag) keeps every assignment live, so the mutation gate has no dead store to
 * rewrite.
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
        $afterSeparator = false;

        foreach ($lines as $line) {
            if (preg_match('/^## \d+\.\s+(.+)/', $line, $matches) === 1) {
                $current = RoadmapTableCells::normalise($matches[1]);
                $counts[$current] = 0;
                $afterSeparator = false;

                continue;
            }

            if (str_starts_with($line, '## ')) {
                $current = null;

                continue;
            }

            if ($current === null) {
                $afterSeparator = false;

                continue;
            }

            if (!str_starts_with($line, '|')) {
                $afterSeparator = false;

                continue;
            }

            if (RoadmapTableCells::isSeparator(RoadmapTableCells::cells($line))) {
                $afterSeparator = true;

                continue;
            }

            if (!$afterSeparator) {
                continue;
            }

            ++$counts[$current];
        }

        return $counts;
    }
}
