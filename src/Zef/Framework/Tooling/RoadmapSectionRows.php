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
 * once a separator has been seen. A run of `|` rows is one table block; any
 * other line ends the block, so a prose line between two tables starts a new
 * one ({@see RoadmapTableBlock} counts a block's data rows).
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
        $title = null;
        $table = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '|')) {
                if ($title === null) {
                    continue;
                }

                $table[] = $line;

                continue;
            }

            self::record($counts, $title, $table);
            $table = [];

            if (!str_starts_with($line, '## ')) {
                continue;
            }

            $title = RoadmapSectionHeading::title($line);

            if ($title === null) {
                continue;
            }

            $counts[$title] = 0;
        }

        self::record($counts, $title, $table);

        return $counts;
    }

    /**
     * Adds the block's data rows to the section's count. A non-numbered heading
     * (a null title) contributes nothing.
     *
     * @param array<string, int> $counts
     * @param list<string>       $table
     */
    private static function record(array &$counts, ?string $title, array $table): void
    {
        if ($title === null) {
            return;
        }

        $counts[$title] += RoadmapTableBlock::dataRows($table);
    }
}
