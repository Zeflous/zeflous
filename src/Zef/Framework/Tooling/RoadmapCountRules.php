<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The count-consistency rule of the roadmap document gate.
 *
 * It lives in its own class for the same reason {@see RoadmapTableRules} and
 * {@see RoadmapCommitRules} do: the repository's architecture gate
 * (`php-codearch-config.yaml`, `tooComplex.difficulty: 20`) scores a class that
 * carries several operator varieties above the ceiling, so each file owns one
 * focused review surface.
 *
 * The rule closes the class of defect that let the `Summary` table drift away
 * from the sections it summarises: the structural rules only check that a row
 * is closed and that the columns line up, so a `Summary` count that no longer
 * matched its section passed CI. The `Summary` table is a *derived* view of the
 * append-only section tables, so every count must equal the number of data rows
 * in the section it names, and the `Total merged PRs` row must equal the sum of
 * the area counts.
 *
 * The rule is deliberately scoped to `ROADMAP-HISTORY.md` (the file that owns
 * the `Summary` table); a document without a `Summary` table is not judged.
 */
final class RoadmapCountRules
{
    /**
     * The label of the total row, compared after normalisation.
     */
    private const string TOTAL_LABEL = 'totalmergedprs';

    /**
     * Invariant E: every `Summary` count equals the number of data rows in the
     * section it names, and the total equals the sum of the area counts.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function inconsistentCounts(string $name, array $lines): array
    {
        $summary = RoadmapSummaryTable::counts($lines);

        $sectionRows = RoadmapSectionRows::counts($lines);
        $failures = [];
        $sum = 0;
        $total = null;

        foreach ($summary as $label => $count) {
            if (RoadmapTableCells::normalise($label) === self::TOTAL_LABEL) {
                $total = $count;

                continue;
            }

            $sum += $count;
            $failures = array_merge($failures, self::areaFailure($name, $label, $count, $sectionRows));
        }

        if ($total !== null && $total !== $sum) {
            $failures[] = \sprintf(
                '%s Summary total is %d but the area counts sum to %d.',
                $name,
                $total,
                $sum,
            );
        }

        return $failures;
    }

    /**
     * The failure for one area row, or an empty list when it agrees with its
     * section.
     *
     * @param array<string, int> $sectionRows
     *
     * @return list<string>
     */
    private static function areaFailure(string $name, string $label, int $count, array $sectionRows): array
    {
        $rows = RoadmapSectionLookup::rowsFor($label, $sectionRows);

        if ($rows === null) {
            return [\sprintf(
                '%s has a Summary row "%s" with no matching numbered section.',
                $name,
                $label,
            )];
        }

        if ($rows === $count) {
            return [];
        }

        return [\sprintf(
            '%s Summary says "%s" = %d but its section has %d data row(s).',
            $name,
            $label,
            $count,
            $rows,
        )];
    }
}
