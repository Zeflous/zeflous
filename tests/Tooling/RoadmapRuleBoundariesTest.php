<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RepeatedWordRun;
use Zef\Framework\Tooling\RoadmapContradictionRules;
use Zef\Framework\Tooling\RoadmapCountRules;
use Zef\Framework\Tooling\RoadmapHistoryRows;
use Zef\Framework\Tooling\RoadmapPhraseRules;
use Zef\Framework\Tooling\RoadmapSectionLookup;
use Zef\Framework\Tooling\RoadmapSectionRows;
use Zef\Framework\Tooling\RoadmapSummaryTable;
use Zef\Framework\Tooling\RoadmapTableCells;

/**
 * The boundary cases of the roadmap-gate helpers, written to pin every branch
 * the mutation gate can rewrite.
 *
 * @internal
 */
#[CoversClass(RepeatedWordRun::class)]
#[CoversClass(RoadmapTableCells::class)]
#[CoversClass(RoadmapSectionRows::class)]
#[CoversClass(RoadmapSummaryTable::class)]
#[CoversClass(RoadmapSectionLookup::class)]
#[CoversClass(RoadmapHistoryRows::class)]
#[CoversClass(RoadmapCountRules::class)]
#[CoversClass(RoadmapPhraseRules::class)]
#[CoversClass(RoadmapContradictionRules::class)]
final class RoadmapRuleBoundariesTest extends TestCase
{
    public function testRepeatedRunFindsARepeatThatStartsAtTheLastWindow(): void
    {
        // The repeat begins exactly at the final window (start == limit), so a
        // loop that stops one short of the limit would miss it.
        self::assertSame('a b c d e f', RepeatedWordRun::first('a b c d e f a b c d e f'));
    }

    public function testRepeatedRunFoldsUnicodeBeforeTokenising(): void
    {
        // U+212A KELVIN SIGN lower-cases to ASCII "k" only under the
        // multibyte fold; a byte fold leaves it non-ASCII and finds no words.
        self::assertSame('k a b c d e', RepeatedWordRun::first("\u{212A} a b c d e f \u{212A} a b c d e f"));
    }

    public function testCellsDropsTheEmptyEdgesOfARow(): void
    {
        self::assertSame(['a'], RoadmapTableCells::cells('| a |'));
    }

    public function testIsSeparatorRejectsAnEmptyList(): void
    {
        self::assertTrue(RoadmapTableCells::isSeparator([]));
    }

    public function testIsSeparatorRejectsAShortDashRun(): void
    {
        self::assertFalse(RoadmapTableCells::isSeparator(['--']));
    }

    public function testNormaliseFoldsUnicodeBeforeStripping(): void
    {
        // U+212A folds to "k" only under the multibyte fold.
        self::assertSame('k', RoadmapTableCells::normalise("\u{212A}"));
    }

    public function testFirstWordFoldsUnicodeBeforeMatching(): void
    {
        self::assertSame('k', RoadmapTableCells::firstWord("\u{212A}"));
    }

    public function testSectionRowsCountsOnlyDataRows(): void
    {
        $lines = [
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
            '| b | #2 |',
            '',
            '## 2. Other',
            '',
            '| Work item | PR |',
            '|---|---|',
        ];

        self::assertSame(['httplayer' => 2, 'other' => 0], RoadmapSectionRows::counts($lines));
    }

    public function testSectionRowsIgnoresATableBeforeAnySection(): void
    {
        $lines = [
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| b | #2 |',
        ];

        self::assertSame(['httplayer' => 1], RoadmapSectionRows::counts($lines));
    }

    public function testSectionRowsResetsTheTableOnANonTableLine(): void
    {
        $lines = [
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
            'prose between two tables',
            '| b | #2 |',
        ];

        // The prose line ends the table, so the following row is a new header.
        self::assertSame(['httplayer' => 1], RoadmapSectionRows::counts($lines));
    }

    public function testSummaryTableSkipsARowWithoutAnInteger(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| HTTP Layer | twelve |',
            '| Container | 3 |',
        ];

        self::assertSame(['Container' => 3], RoadmapSummaryTable::counts($lines));
    }

    public function testSummaryTableStopsAtTheNextHeading(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| Container | 3 |',
            '## 1. HTTP Layer',
            '| Area | Count |',
            '|---|---|',
            '| HTTP Layer | 12 |',
        ];

        self::assertSame(['Container' => 3], RoadmapSummaryTable::counts($lines));
    }

    public function testSummaryTableReadsABacktickedCount(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| **Total merged PRs** | **110** |',
        ];

        self::assertSame(['**Total merged PRs**' => 110], RoadmapSummaryTable::counts($lines));
    }

    public function testSectionLookupMatchesByFirstWord(): void
    {
        self::assertSame(3, RoadmapSectionLookup::rowsFor('Roadmap docs', ['roadmapdocumentation' => 3]));
    }

    public function testSectionLookupRejectsAnAmbiguousFirstWord(): void
    {
        self::assertNull(RoadmapSectionLookup::rowsFor('HTTP', ['http' => 3, 'httplayer' => 1]));
    }

    public function testHistoryRowsReadsTheTriple(): void
    {
        $lines = ['| a | #256 | 2026-10-11 | `e0d5a32f8` |'];

        self::assertSame([['2026-10-11', 256, 'e0d5a32f8']], RoadmapHistoryRows::rows($lines));
    }

    public function testHistoryRowsRejectsARowWithoutADate(): void
    {
        $lines = ['| a | #256 | `e0d5a32f8` |'];

        self::assertSame([], RoadmapHistoryRows::rows($lines));
    }

    public function testHistoryRowsRejectsARowWithoutACommit(): void
    {
        $lines = ['| a | #256 | 2026-10-11 |'];

        self::assertSame([], RoadmapHistoryRows::rows($lines));
    }

    public function testHistoryRowsKeepsTheFirstCommitWhenTwoArePresent(): void
    {
        $lines = ['| a | #256 | 2026-10-11 | `e0d5a32f8` | `ffffffff` |'];

        self::assertSame([['2026-10-11', 256, 'e0d5a32f8']], RoadmapHistoryRows::rows($lines));
    }

    public function testCountRuleIgnoresADocumentWithoutASummary(): void
    {
        self::assertSame([], RoadmapCountRules::inconsistentCounts('CLEAN.md', ['# Title']));
    }

    public function testCountRuleReportsATotalThatDoesNotMatchTheSum(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| HTTP Layer | 1 |',
            '| **Total merged PRs** | **9** |',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame(
            ['BROKEN.md Summary total is 9 but the area counts sum to 1.'],
            RoadmapCountRules::inconsistentCounts('BROKEN.md', $lines),
        );
    }

    public function testCountRuleReportsASummaryRowWithoutASection(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| Widgets | 2 |',
        ];

        self::assertSame(
            ['BROKEN.md has a Summary row "Widgets" with no matching numbered section.'],
            RoadmapCountRules::inconsistentCounts('BROKEN.md', $lines),
        );
    }

    public function testPhraseRuleIgnoresANonTableLine(): void
    {
        self::assertSame([], RoadmapPhraseRules::duplicatedPhrases('CLEAN.md', ['a b c d e f a b c d e f']));
    }

    public function testContradictionRuleIgnoresABulletBeforeTheSection(): void
    {
        // The bullet precedes the `## Not yet done` heading, so it is not judged
        // even though it names an area that has merged rows.
        $lines = [
            '- **HTTP Layer** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }

    public function testContradictionRuleIgnoresAnUnstartedAreaWithNoRows(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- **Widgets** — not started.',
            '## 1. Widgets',
            '',
            '| Work item | PR |',
            '|---|---|',
        ];

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }

    public function testContradictionRuleIgnoresAClaimThatDoesNotOpenWithTheMarker(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- **HTTP Layer** — the handler chain is not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }

    public function testContradictionRuleReportsAnUnstartedAreaWithRows(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- **HTTP Layer** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame(
            ['BROKEN.md:3 declares "HTTP Layer" not started, but its section has 1 merged row(s).'],
            RoadmapContradictionRules::unstartedAreasWithMergedWork('BROKEN.md', $lines),
        );
    }
}
