<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Regression gate for the integrity of the roadmap / delivery documents.
 *
 * It is the automated guard for the class of defect that shipped a malformed
 * Markdown table in `ROADMAP-HISTORY.md`: a row that should have ended with a
 * trailing `|` instead swallowed the `---` separator (the separator was
 * appended to the row line rather than standing on its own), which broke the
 * table and silently glued a horizontal rule onto a data row. The defect was
 * invisible to every other lane -- no PHP is involved, no test parsed the
 * document -- so it survived CI and was found only by a human reading the
 * rendered file.
 *
 * Three invariants are enforced, each on every audited document:
 *
 *  A. **Closed rows.** A line that starts a table row (`|`) must also close it
 *     (`|` as its last non-whitespace character). The shipped defect violated
 *     exactly this: the row ended `...|---`.
 *  B. **Uniform tables.** Every block of contiguous table rows must carry the
 *     same column count. A mis-glued separator changes the cell count, so the
 *     block no longer renders as one table.
 *  C. **Headings are separated.** A heading (`#`..`######`) must not sit
 *     directly under a table row; a blank line has to separate a table from
 *     the heading that follows it.
 *
 * The gate is pure: it evaluates a {@see RoadmapDocReport} and returns a
 * {@see GateResult}, so it is fully unit-testable and needs no filesystem.
 */
final readonly class RoadmapDocGate
{
    public function evaluate(RoadmapDocReport $roadmapDocReport): GateResult
    {
        $failures = [];

        foreach ($roadmapDocReport->documents as $name => $contents) {
            $failures = [...$failures, ...$this->failures($name, $contents)];
        }

        if ($failures !== []) {
            return GateResult::failed(\sprintf(
                'Roadmap document gate FAILED: %s',
                implode(' ', $failures),
            ));
        }

        return GateResult::passed(\sprintf(
            'Roadmap document gate PASSED: %d document(s) audited.',
            $roadmapDocReport->count(),
        ));
    }

    /**
     * @return list<string>
     */
    private function failures(string $name, string $contents): array
    {
        $lines = explode("\n", $contents);

        return [
            ...$this->unclosedRows($name, $lines),
            ...$this->raggedBlocks($name, $lines),
            ...$this->gluedHeadings($name, $lines),
        ];
    }

    /**
     * Invariant A: a table row must be closed with a trailing `|`.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function unclosedRows(string $name, array $lines): array
    {
        $failures = [];

        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, '|') || str_ends_with(mb_rtrim($line), '|')) {
                continue;
            }

            $failures[] = \sprintf(
                '%s:%d has an unclosed table row (a table row must end with "|").',
                $name,
                $index + 1,
            );
        }

        return $failures;
    }

    /**
     * Invariant B: every contiguous block of table rows has a uniform column
     * count. Raggedness is reported on the line that ENDS the block (the first
     * line that is not a table row), which is the boundary a reader sees as a
     * table that no longer lines up. The sentinel line appended to the end
     * flushes a table that reaches the last line of the document.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function raggedBlocks(string $name, array $lines): array
    {
        $failures = [];
        $columns = [];

        foreach ([...$lines, ''] as $index => $line) {
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
     * Invariant C: a heading must not be glued directly under a table row.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function gluedHeadings(string $name, array $lines): array
    {
        $failures = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            if (preg_match('/^#{1,6} /', $line) !== 1 || !str_starts_with($lines[$index - 1], '|')) {
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
