<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapCommitRules;

/**
 * @internal
 */
#[CoversClass(RoadmapCommitRules::class)]
final class RoadmapCommitRulesTest extends TestCase
{
    public function testReportsAPlaceholderCommitCell(): void
    {
        // The shipped defect: a literal `PENDING` placeholder in the Commit
        // column. The structural rules accept it; this rule must not.
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n| a | #1 | 2026-10-07 | `PENDING` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapCommitRules::invalidCommitCells('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md:3 has a Commit cell that is not a merge SHA ("`PENDING`").',
            $failures[0],
        );
    }

    public function testPassesOnRealMergeShas(): void
    {
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n| b | #2 | 2026-10-08 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapCommitRules::invalidCommitCells('CLEAN.md', $lines));
    }

    public function testReportsEveryInvalidCellInOneTable(): void
    {
        // Two bad cells in ONE table, so the failure list carries more than one
        // entry (a single-entry list is indistinguishable from one that dropped
        // its tail, and that is exactly the mutant to catch).
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `PENDING` |\n| b | #2 | 2026-10-08 | `TBD` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapCommitRules::invalidCommitCells('BROKEN.md', $lines);

        self::assertCount(2, $failures);
        self::assertSame(
            'BROKEN.md:3 has a Commit cell that is not a merge SHA ("`PENDING`").',
            $failures[0],
        );
        self::assertSame(
            'BROKEN.md:4 has a Commit cell that is not a merge SHA ("`TBD`").',
            $failures[1],
        );
    }

    public function testIgnoresTablesWithoutACommitColumn(): void
    {
        // A table whose header has no `Commit` column is out of scope, even if
        // a data cell happens to read `PENDING`.
        $document = "| Area | Status |\n|---|---|\n| HTTP | PENDING |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapCommitRules::invalidCommitCells('CLEAN.md', $lines));
    }

    public function testIgnoresACommitWordInTheFirstColumnOfAProseTable(): void
    {
        // The "How to read this file" table names `Commit` in its FIRST column
        // as a row label, not as a column header. It must not be treated as a
        // Commit column.
        $document = "| Column | Meaning |\n|---|---|\n| Commit | Merge commit SHA (short) |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapCommitRules::invalidCommitCells('CLEAN.md', $lines));
    }

    public function testResetsTheColumnWhenATableEnds(): void
    {
        // A Commit table followed by a non-Commit table: the second table must
        // not inherit the first table's Commit column index.
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n\n| Area | Status |\n|---|---|\n| HTTP | PENDING |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapCommitRules::invalidCommitCells('CLEAN.md', $lines));
    }

    public function testAcceptsAFullFortyCharacterSha(): void
    {
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `e0d5a32f85b369d564dfbe85e204ad797b98675a` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapCommitRules::invalidCommitCells('CLEAN.md', $lines));
    }
}
