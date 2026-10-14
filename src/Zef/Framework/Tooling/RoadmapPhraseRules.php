<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The phrase-duplication rule of the roadmap document gate.
 *
 * It lives in its own class for the same reason {@see RoadmapTableRules},
 * {@see RoadmapCommitRules} and {@see RoadmapCountRules} do: the repository's
 * architecture gate (`php-codearch-config.yaml`, `tooComplex.difficulty: 20`)
 * scores a class that carries several operator varieties above the ceiling, so
 * each file owns one focused review surface.
 *
 * The rule closes the class of defect that shipped the same clause twice inside
 * one evidence cell of `ROADMAP-STATUS.md` (the PSR-7 `Uri` value object was
 * named twice, and the Fase 2 runtime adapter was named twice): the structural
 * rules only check that a row is closed and that the columns line up, so a cell
 * that repeats a six-word run passed CI. A repeated run of six or more words
 * inside a single cell is never intentional prose -- it is a copy/paste defect
 * -- so the gate fails on it.
 *
 * The rule is deliberately scoped to the roadmap documents; a document without
 * table cells is not judged.
 */
final class RoadmapPhraseRules
{
    /**
     * Invariant F: no single table cell repeats a run of six or more words.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function duplicatedPhrases(string $name, array $lines): array
    {
        $failures = [];

        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, '|')) {
                continue;
            }

            foreach (RoadmapTableCells::cells($line) as $cell) {
                $run = RepeatedWordRun::first($cell);

                if ($run === null) {
                    continue;
                }

                $failures[] = \sprintf(
                    '%s:%d repeats the phrase "%s" inside one cell.',
                    $name,
                    $index + 1,
                    $run,
                );
            }
        }

        return $failures;
    }
}
