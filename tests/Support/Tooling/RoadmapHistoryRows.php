<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Reads the `[merge date, pull-request number, commit]` triple of every history
 * row of a roadmap document.
 *
 * Two rules consume it: the pointer-freshness rule
 * ({@see RoadmapPointerRules}) needs the newest row to know which commit the
 * `Last updated` pointer must name, and the pin-freshness rule
 * ({@see RoadmapPinFreshness}) needs the ledger of recorded pull-request
 * numbers to accept the merge machinery's landing commits.
 */
final class RoadmapHistoryRows
{
    /**
     * The triple of every row that carries both a date and a backticked SHA.
     *
     * @param list<string> $lines
     *
     * @return list<array{0: string, 1: int, 2: string}>
     */
    public static function rows(array $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            if (!str_starts_with($line, '|')) {
                continue;
            }

            $row = self::row($line);

            if ($row === null) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The pull-request numbers that carry a history row: the document's ledger
     * of recorded merges. A row without a pull-request cell records number 0
     * and contributes nothing -- 0 is not a pull-request number, and accepting
     * it would let a walk subject shaped "(#0)" piggyback on a numberless row.
     *
     * @param list<string> $lines
     *
     * @return list<int>
     */
    public static function pullRequests(array $lines): array
    {
        $numbers = [];

        foreach (self::rows($lines) as $row) {
            if ($row[1] === 0) {
                continue;
            }

            $numbers[] = $row[1];
        }

        return $numbers;
    }

    /**
     * The triple of one row, or null when it is not a history row.
     *
     * @return null|array{0: string, 1: int, 2: string}
     */
    private static function row(string $line): ?array
    {
        $date = '';
        $pr = 0;
        $commit = '';

        foreach (RoadmapTableCells::cells($line) as $cell) {
            if ($date === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cell) === 1) {
                $date = $cell;
            }

            if ($pr === 0 && preg_match('/^#(\d+)$/', $cell, $prMatches) === 1) {
                $pr = (int) $prMatches[1];
            }

            if ($commit !== '' || preg_match('/^`([0-9a-f]{7,40})`$/', $cell, $commitMatches) !== 1) {
                continue;
            }

            $commit = $commitMatches[1];
        }

        if ($date === '' || $commit === '') {
            return null;
        }

        return [$date, $pr, $commit];
    }
}
