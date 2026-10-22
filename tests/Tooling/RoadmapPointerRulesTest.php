<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapHistoryRows;
use Zef\Framework\Tooling\RoadmapNewestRow;
use Zef\Framework\Tooling\RoadmapPointerRules;
use Zef\Framework\Tooling\RoadmapTableCells;

/**
 * @internal
 */
#[CoversClass(RoadmapPointerRules::class)]
#[CoversClass(RoadmapHistoryRows::class)]
#[CoversClass(RoadmapNewestRow::class)]
#[CoversClass(RoadmapTableCells::class)]
final class RoadmapPointerRulesTest extends TestCase
{
    public function testReportsAPointerOlderThanTheNewestRow(): void
    {
        // The shipped defect: the pointer named the #298 row while the #303 row
        // was already present. The structural rules accept it; this rule must
        // not.
        $document = "**Last updated:** 2026-10-12 · `main` @ `eb1c10a`\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #298 | 2026-10-12 | `eb1c10a` |\n"
            . "| b | #303 | 2026-10-13 | `9baaa4b` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapPointerRules::stalePointers('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md "Last updated" points at `eb1c10a`, but the newest recorded row is `9baaa4b` (2026-10-13).',
            $failures[0],
        );
    }

    public function testPassesWhenThePointerNamesTheNewestRow(): void
    {
        $document = "**Last updated:** 2026-10-13 · `main` @ `9baaa4b`\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #298 | 2026-10-12 | `eb1c10a` |\n"
            . "| b | #303 | 2026-10-13 | `9baaa4b` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapPointerRules::stalePointers('CLEAN.md', $lines));
    }

    public function testBreaksATieOnTheNewestDateByTheGreatestPullRequestNumber(): void
    {
        // Two rows share the newest date; the pointer must name the greater PR.
        $document = "**Last updated:** 2026-10-13 · `main` @ `aaaaaaa`\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #300 | 2026-10-13 | `aaaaaaa` |\n"
            . "| b | #303 | 2026-10-13 | `bbbbbbb` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapPointerRules::stalePointers('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md "Last updated" points at `aaaaaaa`, but the newest recorded row is `bbbbbbb` (2026-10-13).',
            $failures[0],
        );
    }

    public function testIgnoresADocumentWithoutAPointer(): void
    {
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapPointerRules::stalePointers('CLEAN.md', $lines));
    }

    public function testIgnoresADocumentWithoutAHistoryTable(): void
    {
        $document = "**Last updated:** 2026-10-13 · `main` @ `9baaa4b`\n\nNo table here.\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapPointerRules::stalePointers('CLEAN.md', $lines));
    }

    public function testPointerAccessorReturnsTheDeclaredPointer(): void
    {
        $lines = explode("\n", "**Last updated:** 2026-10-13 · `main` @ `9baaa4b`\n");

        self::assertSame(['2026-10-13', '9baaa4b'], RoadmapPointerRules::pointer($lines));
    }

    public function testPointerAccessorReturnsNullWithoutAPointer(): void
    {
        self::assertNull(RoadmapPointerRules::pointer(explode("\n", "# Title\n")));
    }

    public function testPullRequestsListsEveryNumberedRow(): void
    {
        // The ledger of recorded merges: every row that carries a
        // pull-request cell contributes its number, in document order.
        $document = "**Last updated:** 2026-10-13 · `main` @ `9baaa4b`\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #298 | 2026-10-12 | `eb1c10a` |\n"
            . "| b | #303 | 2026-10-13 | `9baaa4b` |\n";
        $lines = explode("\n", $document);

        self::assertSame([298, 303], RoadmapHistoryRows::pullRequests($lines));
    }

    public function testPullRequestsExcludesNumberlessRows(): void
    {
        // A row without a pull-request cell records number 0 and contributes
        // nothing: 0 is not a pull-request number, and accepting it would let
        // a walk subject shaped "(#0)" piggyback on a numberless row.
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a legacy row | missing | 2026-10-07 | `f6aa0dbeb8` |\n"
            . "| b | #303 | 2026-10-13 | `9baaa4b` |\n";
        $lines = explode("\n", $document);

        self::assertSame([303], RoadmapHistoryRows::pullRequests($lines));
    }

    public function testPullRequestsWithoutAnyRowIsEmpty(): void
    {
        self::assertSame([], RoadmapHistoryRows::pullRequests(explode("\n", "# Title\n\nNo table here.\n")));
    }
}
