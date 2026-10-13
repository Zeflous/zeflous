<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapContradictionRules;
use Zef\Framework\Tooling\RoadmapCountRules;
use Zef\Framework\Tooling\RoadmapHistoryRows;
use Zef\Framework\Tooling\RoadmapNewestRow;
use Zef\Framework\Tooling\RoadmapPhraseRules;
use Zef\Framework\Tooling\RoadmapSectionLookup;
use Zef\Framework\Tooling\RoadmapSectionRows;
use Zef\Framework\Tooling\RoadmapSummaryTable;

/**
 * The cases that pin the control flow of the roadmap-gate helpers, so the
 * mutation gate cannot rewrite a `continue` into a `break` or a comparison
 * into its non-strict twin without a test noticing.
 *
 * @internal
 */
#[CoversClass(RoadmapContradictionRules::class)]
#[CoversClass(RoadmapCountRules::class)]
#[CoversClass(RoadmapHistoryRows::class)]
#[CoversClass(RoadmapNewestRow::class)]
#[CoversClass(RoadmapPhraseRules::class)]
#[CoversClass(RoadmapSectionLookup::class)]
#[CoversClass(RoadmapSectionRows::class)]
#[CoversClass(RoadmapSummaryTable::class)]
final class RoadmapRuleMutationTest extends TestCase
{
    public function testContradictionRuleKeepsScanningAfterANonUnstartedBullet(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- **Widgets** — shipped.',
            '- **HTTP Layer** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertCount(1, RoadmapContradictionRules::unstartedAreasWithMergedWork('BROKEN.md', $lines));
    }

    public function testContradictionRuleKeepsScanningAfterAnUnstartedAreaWithoutRows(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- **Widgets** — not started.',
            '- **HTTP Layer** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertCount(1, RoadmapContradictionRules::unstartedAreasWithMergedWork('BROKEN.md', $lines));
    }

    public function testContradictionRuleReportsEveryUnstartedAreaWithRows(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- **HTTP Layer** — not started.',
            '- **Container** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
            '## 2. Container',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| b | #2 |',
        ];

        self::assertCount(2, RoadmapContradictionRules::unstartedAreasWithMergedWork('BROKEN.md', $lines));
    }

    public function testCountRuleKeepsScanningAfterTheTotalRow(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| **Total merged PRs** | **1** |',
            '| HTTP Layer | 1 |',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertSame([], RoadmapCountRules::inconsistentCounts('CLEAN.md', $lines));
    }

    public function testCountRuleSumsEveryArea(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| HTTP Layer | 1 |',
            '| Container | 1 |',
            '| **Total merged PRs** | **2** |',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
            '## 2. Container',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| b | #2 |',
        ];

        self::assertSame([], RoadmapCountRules::inconsistentCounts('CLEAN.md', $lines));
    }

    public function testHistoryRowsRejectsAPullRequestCellWithAPrefix(): void
    {
        $lines = ['| a | x#256 | 2026-10-11 | `e0d5a32f8` |'];

        self::assertSame([['2026-10-11', 0, 'e0d5a32f8']], RoadmapHistoryRows::rows($lines));
    }

    public function testHistoryRowsRejectsACommitCellWithAPrefix(): void
    {
        $lines = ['| a | #256 | 2026-10-11 | x`e0d5a32f8` |'];

        self::assertSame([], RoadmapHistoryRows::rows($lines));
    }

    public function testNewestRowKeepsTheHigherPullRequestOnTheSameDate(): void
    {
        $rows = [
            ['2026-10-12', 303, 'cccccccc'],
            ['2026-10-12', 259, 'bbbbbbbb'],
        ];

        self::assertSame(['2026-10-12', 303, 'cccccccc'], RoadmapNewestRow::of($rows));
    }

    public function testNewestRowKeepsTheFirstRowOnATie(): void
    {
        $rows = [
            ['2026-10-12', 303, 'cccccccc'],
            ['2026-10-12', 303, 'dddddddd'],
        ];

        self::assertSame(['2026-10-12', 303, 'cccccccc'], RoadmapNewestRow::of($rows));
    }

    public function testContradictionRuleKeepsScanningAfterANonBulletLine(): void
    {
        $lines = [
            '## Not yet done',
            '',
            'A prose line that is not a bullet.',
            '- **HTTP Layer** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertCount(1, RoadmapContradictionRules::unstartedAreasWithMergedWork('BROKEN.md', $lines));
    }

    public function testNewestRowKeepsScanningAfterAnOlderRow(): void
    {
        $rows = [
            ['2026-10-12', 303, 'cccccccc'],
            ['2026-10-10', 999, 'dddddddd'],
            ['2026-10-12', 400, 'eeeeeeee'],
        ];

        self::assertSame(['2026-10-12', 400, 'eeeeeeee'], RoadmapNewestRow::of($rows));
    }

    public function testSectionRowsCountsADataRowAfterAHeaderOnlyTable(): void
    {
        $lines = [
            '## 1. HTTP Layer',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
            '| b | #2 |',
        ];

        self::assertSame(['httplayer' => 2], RoadmapSectionRows::counts($lines));
    }

    public function testContradictionRuleKeepsScanningAfterAMalformedBullet(): void
    {
        $lines = [
            '## Not yet done',
            '',
            '- **HTTP Layer** without a dash separator',
            '- **HTTP Layer** — not started.',
            '## 1. HTTP Layer',
            '',
            '| Work item | PR |',
            '|---|---|',
            '| a | #1 |',
        ];

        self::assertCount(1, RoadmapContradictionRules::unstartedAreasWithMergedWork('BROKEN.md', $lines));
    }

    public function testHistoryRowsRejectsAPullRequestCellWithATrailingCharacter(): void
    {
        $lines = ['| a | #256x | 2026-10-11 | `e0d5a32f8` |'];

        self::assertSame([['2026-10-11', 0, 'e0d5a32f8']], RoadmapHistoryRows::rows($lines));
    }

    public function testPhraseRuleKeepsScanningAfterANonTableLine(): void
    {
        $lines = [
            'prose before the table',
            '| a b c d e f a b c d e f |',
        ];

        self::assertCount(1, RoadmapPhraseRules::duplicatedPhrases('BROKEN.md', $lines));
    }

    public function testSectionLookupKeepsScanningAfterANonMatchingSection(): void
    {
        self::assertSame(3, RoadmapSectionLookup::rowsFor('http', ['widgets' => 1, 'httplayer' => 3]));
    }

    public function testSectionRowsTreatsTheFirstRowAsAHeader(): void
    {
        $lines = [
            '## 1. HTTP Layer',
            '| a | #1 |',
            '|---|---|',
            '| b | #2 |',
        ];

        self::assertSame(['httplayer' => 1], RoadmapSectionRows::counts($lines));
    }

    public function testSummaryTableRejectsACountThatIsNotLeading(): void
    {
        $lines = [
            '## Summary',
            '| Area | Count |',
            '|---|---|',
            '| HTTP Layer | x12 |',
        ];

        self::assertSame([], RoadmapSummaryTable::counts($lines));
    }
}
