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
 * once a separator has been seen. Each contiguous run of `|` rows is collected
 * as a block and its data rows are added to the section's running count when the
 * block ends ({@see self::flush()}), so every assignment is live and the
 * mutation gate has no dead store to rewrite.
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
        $table = [];

        foreach ($lines as $line) {
            if (preg_match('/^## \d+\.\s+(.+)/', $line, $matches) === 1) {
                self::flush($counts, $current, $table);
                $current = RoadmapTableCells::normalise($matches[1]);
                $counts[$current] = 0;

                continue;
            }

            if (str_starts_with($line, '## ')) {
                self::flush($counts, $current, $table);
                $current = null;

                continue;
            }

            if ($current === null) {
                continue;
            }

            if (!str_starts_with($line, '|')) {
                self::flush($counts, $current, $table);

                continue;
            }

            $table[] = $line;
        }

        self::flush($counts, $current, $table);

        return $counts;
    }

    /**
     * Adds the current block's data rows to the section's count and starts a new
     * block. A block with no separator contributes nothing.
     *
     * @param array<string, int> $counts
     * @param list<string>       $table
     */
    private static function flush(array &$counts, ?string $current, array &$table): void
    {
        if ($current !== null) {
            $counts[$current] += self::dataRows($table);
        }

        $table = [];
    }

    /**
     * The number of data rows in a collected table block: every row after the
     * `---` separator. A block with no separator has no data rows.
     *
     * @param list<string> $table
     */
    private static function dataRows(array $table): int
    {
        $separator = null;

        foreach ($table as $index => $row) {
            if (RoadmapTableCells::isSeparator(RoadmapTableCells::cells($row))) {
                $separator = $index;

                break;
            }
        }

        if ($separator === null) {
            return 0;
        }

        return \count($table) - $separator - 1;
    }
}
