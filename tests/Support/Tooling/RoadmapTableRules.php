<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The two structural table rules of the roadmap document gate.
 *
 * They live apart from {@see RoadmapDocGate} so that neither file carries more
 * operator variety than the repository's architecture gate
 * (`php-codearch-config.yaml`, `tooComplex.difficulty: 20`) allows; the gate
 * class owns "rows are closed", this class owns "tables line up" and
 * "headings are separated".
 *
 * Both rules are pure functions over a document's lines.
 */
final class RoadmapTableRules
{
    /**
     * Invariant B: every contiguous block of table rows has a uniform column
     * count. Raggedness is reported on the line that ENDS the block (the first
     * line that is not a table row). The sentinel line appended to the end
     * flushes a table that reaches the last line of the document.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function raggedBlocks(string $name, array $lines): array
    {
        $failures = [];
        $columns = [];
        $lines[] = '';

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '|')) {
                $columns[] = \count(explode('|', $line));

                continue;
            }

            if (\count(array_unique($columns)) > 1) {
                $failures[] = \sprintf(
                    '%s:%d ends a table whose rows do not all have the same column count.',
                    $name,
                    $index + 1,
                );
            }

            $columns = [];
        }

        return $failures;
    }

    /**
     * Invariant C: a heading must not be glued directly under a table row; a
     * blank line has to separate a table from the heading that follows it.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function gluedHeadings(string $name, array $lines): array
    {
        $failures = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            if (!str_starts_with($line, '#')) {
                continue;
            }

            if (!str_starts_with($lines[$index - 1], '|')) {
                continue;
            }

            $failures[] = \sprintf(
                '%s:%d glues a heading under a table row (insert a blank line).',
                $name,
                $index + 1,
            );
        }

        return $failures;
    }
}
