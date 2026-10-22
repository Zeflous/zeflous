<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Counts the data rows of one collected Markdown table block.
 *
 * It lives in its own class for the same reason the other roadmap rules do:
 * the repository's architecture gate (`php-codearch-config.yaml`,
 * `tooComplex.difficulty: 20`) scores a class that carries several operator
 * varieties above the ceiling, so the block arithmetic is split out of
 * {@see RoadmapSectionRows} and each file stays under it.
 *
 * A block's data rows are every row after the `---` separator; a block with no
 * separator has no data rows.
 */
final class RoadmapTableBlock
{
    /**
     * The number of data rows in a collected table block.
     *
     * @param list<string> $table
     */
    public static function dataRows(array $table): int
    {
        $separators = array_keys(array_filter(
            $table,
            static fn (string $row): bool => RoadmapTableCells::isSeparator(RoadmapTableCells::cells($row)),
        ));

        if ($separators === []) {
            return 0;
        }

        return \count($table) - $separators[0] - 1;
    }
}
