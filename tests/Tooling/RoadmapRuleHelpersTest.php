<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RepeatedWordRun;
use Zef\Framework\Tooling\RoadmapContradictionRules;
use Zef\Framework\Tooling\RoadmapHistoryRows;
use Zef\Framework\Tooling\RoadmapNewestRow;
use Zef\Framework\Tooling\RoadmapSectionLookup;
use Zef\Framework\Tooling\RoadmapSummaryTable;
use Zef\Framework\Tooling\RoadmapTableCells;

/**
 * The edge cases of the shared roadmap-gate helpers.
 *
 * @internal
 */
#[CoversClass(RoadmapTableCells::class)]
#[CoversClass(RoadmapSectionLookup::class)]
#[CoversClass(RoadmapSummaryTable::class)]
#[CoversClass(RoadmapNewestRow::class)]
#[CoversClass(RoadmapHistoryRows::class)]
#[CoversClass(RepeatedWordRun::class)]
#[CoversClass(RoadmapContradictionRules::class)]
final class RoadmapRuleHelpersTest extends TestCase
{
    public function testCellsSplitsAndTrimsARow(): void
    {
        self::assertSame(['a', 'b'], RoadmapTableCells::cells('| a | b |'));
    }

    /**
     * A Windows contributor without `.gitattributes` commits CRLF line
     * endings: the trailing carriage return must not survive the row's
     * edges, or every separator row grows an empty cell -- the count
     * rules silently judge nothing and the commit rules false-fail the
     * separator as a data row.
     */
    public function testCellsDropsTheCarriageReturnOfACrlfRow(): void
    {
        self::assertSame(['a', 'b'], RoadmapTableCells::cells("| a | b |\r"));
        self::assertTrue(RoadmapTableCells::isSeparator(RoadmapTableCells::cells("|---|---|\r")));
        self::assertSame(2, \count(RoadmapTableCells::cells("|---|---|\r")));
    }

    public function testIsSeparatorAcceptsEveryAlignment(): void
    {
        self::assertTrue(RoadmapTableCells::isSeparator(['---', ':---', '---:']));
        self::assertFalse(RoadmapTableCells::isSeparator(['a', 'b']));
    }

    public function testNormaliseStripsEmphasisAndPunctuation(): void
    {
        self::assertSame('httplayer', RoadmapTableCells::normalise('**HTTP Layer**'));
    }

    public function testFirstWordIsEmptyWithoutAnAlphanumeric(): void
    {
        self::assertSame('', RoadmapTableCells::firstWord('---'));
        self::assertSame('http', RoadmapTableCells::firstWord('**HTTP Layer**'));
    }

    public function testSectionLookupRejectsALabelWithoutAWord(): void
    {
        self::assertNull(RoadmapSectionLookup::rowsFor('---', ['http' => 3]));
    }

    public function testSectionLookupRejectsAnAmbiguousLabel(): void
    {
        self::assertNull(RoadmapSectionLookup::rowsFor('HTTP', ['http' => 3, 'httplayer' => 1]));
    }

    public function testSummaryTableSkipsRowsThatAreNotTwoCells(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count | Note |',
            '|---|---|---|',
            '| HTTP Layer | 12 | extra |',
            '| Container | 3 |',
        ];

        self::assertSame(['Container' => 3], RoadmapSummaryTable::counts($lines));
    }

    public function testNewestRowIsNullWithoutRows(): void
    {
        self::assertNull(RoadmapNewestRow::of([]));
    }

    public function testNewestRowPicksTheGreatestDateThenPullRequest(): void
    {
        $rows = [
            ['2026-10-11', 256, 'aaaaaaaa'],
            ['2026-10-12', 259, 'bbbbbbbb'],
            ['2026-10-12', 303, 'cccccccc'],
            ['2026-10-10', 999, 'dddddddd'],
        ];

        self::assertSame(['2026-10-12', 303, 'cccccccc'], RoadmapNewestRow::of($rows));
    }

    public function testHistoryRowsIgnoreLinesThatAreNotHistoryRows(): void
    {
        $lines = [
            '| Area | Status |',
            '|---|---|',
            '| HTTP | done |',
            '| 2026-10-12 | #303 | `cccccccc` |',
        ];

        self::assertSame([['2026-10-12', 303, 'cccccccc']], RoadmapHistoryRows::rows($lines));
    }

    public function testRepeatedWordRunIgnoresACellShorterThanTheMinimumRun(): void
    {
        self::assertNull(RepeatedWordRun::first('a b c'));
    }

    public function testContradictionRuleIgnoresBulletsThatAreNotAreaClaims(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- a plain bullet without an area',
            '- **HTTP Layer** without a dash separator',
        ];

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }
}
