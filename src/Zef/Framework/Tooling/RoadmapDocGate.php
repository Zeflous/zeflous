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
 * table and glued a horizontal rule onto a data row. No other lane inspects
 * these pure-Markdown documents, so the defect survived CI and was found only
 * by a human reading the rendered file.
 *
 * This class owns the gate's own invariant -- every table row must be CLOSED
 * with a trailing `|` -- and delegates the neighbouring rules to their own
 * focused classes: uniform columns and heading separation to
 * {@see RoadmapTableRules}, the commit-cell rule to {@see RoadmapCommitRules},
 * the derived-count rule to {@see RoadmapCountRules}, the phrase-duplication
 * rule to {@see RoadmapPhraseRules}, the pointer-freshness rule to
 * {@see RoadmapPointerRules} and the cross-section contradiction rule to
 * {@see RoadmapContradictionRules}. The split is not cosmetic: the repository's
 * own architecture gate (`php-codearch-config.yaml`,
 * `tooComplex.difficulty: 20`) scores a single class holding every rule above
 * the difficulty ceiling, so each file carries one focused review surface and
 * stays under it.
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
     * Every rule violation for one document, in a stable order.
     *
     * @return list<string>
     */
    private function failures(string $name, string $contents): array
    {
        $lines = explode("\n", $contents);

        return [
            ...$this->unclosedRows($name, $lines),
            ...RoadmapTableRules::raggedBlocks($name, $lines),
            ...RoadmapTableRules::gluedHeadings($name, $lines),
            ...RoadmapCommitRules::invalidCommitCells($name, $lines),
            ...RoadmapCountRules::inconsistentCounts($name, $lines),
            ...RoadmapPhraseRules::duplicatedPhrases($name, $lines),
            ...RoadmapPointerRules::stalePointers($name, $lines),
            ...RoadmapContradictionRules::unstartedAreasWithMergedWork($name, $lines),
        ];
    }

    /**
     * Invariant A: a table row must be closed with a trailing `|`. The shipped
     * defect violated exactly this -- the row ended `...|---`.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function unclosedRows(string $name, array $lines): array
    {
        $failures = [];

        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, '|')) {
                continue;
            }

            if (str_ends_with(mb_rtrim($line), '|')) {
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
}
