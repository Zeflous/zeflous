<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RepeatedWordRun;
use Zef\Test\Support\Tooling\RoadmapContradictionRules;
use Zef\Test\Support\Tooling\RoadmapCountRules;
use Zef\Test\Support\Tooling\RoadmapHistoryRows;
use Zef\Test\Support\Tooling\RoadmapNewestRow;
use Zef\Test\Support\Tooling\RoadmapSectionRows;
use Zef\Test\Support\Tooling\RoadmapSummaryTable;
use Zef\Test\Support\Tooling\RoadmapTableCells;

/**
 * The boundary cases that pin the behaviour the mutation gate can otherwise
 * rewrite without a test noticing: an off-by-one on a window's lower bound, a
 * scan that stops early after a non-matching row, an accumulator that drops an
 * earlier failure, and the anchors of the two regular expressions.
 *
 * @internal
 */
#[CoversClass(RepeatedWordRun::class)]
#[CoversClass(RoadmapContradictionRules::class)]
#[CoversClass(RoadmapCountRules::class)]
#[CoversClass(RoadmapHistoryRows::class)]
#[CoversClass(RoadmapNewestRow::class)]
#[CoversClass(RoadmapSectionRows::class)]
#[CoversClass(RoadmapSummaryTable::class)]
#[CoversClass(RoadmapTableCells::class)]
final class RoadmapGateMutationGuardsTest extends TestCase
{
    public function testRepeatedRunReturnsNullWhenOnlyTheLastWordRepeats(): void
    {
        // Six words, so the single candidate window starts at offset 0. No
        // six-word run repeats, so the answer is null. A scan that started one
        // word before the first window would read the one-word slice "z" and
        // report it as a repeated phrase.
        self::assertNull(RepeatedWordRun::first('x y z x y z'));
    }

    public function testRepeatedRunKeepsScanningForARepeat(): void
    {
        // The first two windows ("a b c d e f" at 0, "b c d e f c" at 1) never
        // repeat, so the scan must continue until it re-sees a window: the
        // window at offset 6 repeats at offset 12. A scan that stopped after a
        // window it had not seen would answer null.
        self::assertSame(
            'c d e f g h',
            RepeatedWordRun::first('a b c d e f c d e f g h c d e f g h a b c d e f'),
        );
    }

    public function testContradictionRuleIgnoresABulletMarkerPrefixedByProse(): void
    {
        // The line does not open with the bullet marker, so it is not a bullet
        // and must not be judged even though it names an area with merged rows.
        $lines = [
            '## Not yet done',
            '',
            'xx- **HTTP Layer** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }

    public function testCountRuleReportsEveryDivergentArea(): void
    {
        // Two area rows with no matching section must both be reported, so a
        // rule that drops the failures accumulated before the last iteration is
        // caught.
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| HTTP Layer | 1 |',
            '| Widgets | 2 |',
        ];

        self::assertCount(2, RoadmapCountRules::inconsistentCounts('BROKEN.md', $lines));
    }

    public function testSectionRowsCountsTwoSectionsIndependently(): void
    {
        $lines = [
            '## 1. HTTP Layer',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
            '## 2. Widgets',
            '| Work item | PR |',
            '|---|---|',
            '| b | #2 |',
            '| c | #3 |',
        ];

        self::assertSame(['httplayer' => 1, 'widgets' => 2], RoadmapSectionRows::counts($lines));
    }

    public function testIsSeparatorRejectsALeadingCharacter(): void
    {
        // "---" must be the whole cell: a cell that merely contains a run of
        // three dashes is a data cell, not the separator row.
        self::assertFalse(RoadmapTableCells::isSeparator(['a---']));
        self::assertFalse(RoadmapTableCells::isSeparator([':a---']));
    }

    public function testHistoryRowsRejectsACommitCellWithATrailingCharacter(): void
    {
        // The commit cell must be exactly one backticked SHA, so a trailing
        // character makes the row something other than a history row. A
        // pattern without its end anchor would read the leading SHA anyway.
        self::assertSame([], RoadmapHistoryRows::rows(['| a | #256 | 2026-10-11 | `e0d5a32f8`x |']));
    }

    public function testNewestRowKeepsTheHigherPullRequestOnTheSameDate(): void
    {
        // Both rows share the merge date, so the newer one is the row with the
        // higher pull-request number -- not simply the last row seen.
        $rows = [
            ['2026-10-12', 303, 'aaaaaaaa'],
            ['2026-10-12', 100, 'bbbbbbbb'],
        ];

        self::assertSame(['2026-10-12', 303, 'aaaaaaaa'], RoadmapNewestRow::of($rows));
    }

    public function testSectionRowsAcceptsALineEndedSection(): void
    {
        // PHP treats "$" as end-of-subject-or-before-final-newline, so a line
        // that carries a carriage return from a CRLF document must still open
        // its section. A pattern that anchored on "$" would miss it.
        $lines = [
            "## 1. HTTP Layer\r",
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame(['httplayer' => 1], RoadmapSectionRows::counts($lines));
    }

    public function testSummaryTableIgnoresATableBeforeTheSummaryHeading(): void
    {
        // A table above the `## Summary` heading is not part of the summary, so
        // it must not contribute a count.
        $lines = [
            '| Area | Count |',
            '|---|---|',
            '| Widgets | 7 |',
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| Container | 3 |',
        ];

        self::assertSame(['Container' => 3], RoadmapSummaryTable::counts($lines));
    }

    public function testIsSeparatorRejectsATrailingCharacter(): void
    {
        // A separator cell must be nothing but the dash run and its optional
        // colons, so a trailing character disqualifies it. A pattern without
        // its end anchor would match the leading dashes and accept the cell.
        self::assertFalse(RoadmapTableCells::isSeparator(['---x']));
    }

    public function testHistoryRowsRejectsADateCellWithATrailingCharacter(): void
    {
        $lines = ['| a | #256 | 2026-10-11x | `e0d5a32f8` |'];

        // A date cell must be exactly `YYYY-MM-DD`, so a trailing character
        // leaves the row without a date. A pattern without its end anchor
        // would read the leading date and return the row.
        self::assertSame([], RoadmapHistoryRows::rows($lines));
    }

    public function testHistoryRowsRejectsADateCellWithALeadingCharacter(): void
    {
        $lines = ['| a | #256 | x2026-10-11 | `e0d5a32f8` |'];

        // The date must open the cell. A pattern without its start anchor
        // would find the date anywhere and return the row.
        self::assertSame([], RoadmapHistoryRows::rows($lines));
    }

    public function testSectionRowsIgnoresAMidLineSectionHeading(): void
    {
        // A numbered section heading must open the line, so a `## n.` that
        // appears after other text does not open a section. A pattern without
        // its start anchor would open one and count the following table.
        $lines = [
            'note ## 1. HTTP Layer',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame([], RoadmapSectionRows::counts($lines));
    }
}
