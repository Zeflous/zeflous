<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapCountRules;
use Zef\Framework\Tooling\RoadmapSectionLookup;
use Zef\Framework\Tooling\RoadmapSectionRows;
use Zef\Framework\Tooling\RoadmapSummaryTable;
use Zef\Framework\Tooling\RoadmapTableCells;

/**
 * @internal
 */
#[CoversClass(RoadmapCountRules::class)]
#[CoversClass(RoadmapSectionRows::class)]
#[CoversClass(RoadmapSummaryTable::class)]
#[CoversClass(RoadmapSectionLookup::class)]
#[CoversClass(RoadmapTableCells::class)]
final class RoadmapCountRulesTest extends TestCase
{
    public function testReportsASummaryCountThatDoesNotMatchItsSection(): void
    {
        // The shipped defect: the Summary table drifted away from the section
        // it summarises. The structural rules accept it; this rule must not.
        $document = "## Summary\n\n| Area | Count |\n|------|-------|\n| Container | 3 |\n"
            . "| **Total merged PRs** | **3** |\n\n## 1. Container\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n| b | #2 | 2026-10-08 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapCountRules::inconsistentCounts('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md Summary says "Container" = 3 but its section has 2 data row(s).',
            $failures[0],
        );
    }

    public function testPassesWhenEverySummaryCountMatchesItsSection(): void
    {
        $document = "## Summary\n\n| Area | Count |\n|------|-------|\n| Container | 2 |\n"
            . "| **Total merged PRs** | **2** |\n\n## 1. Container\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n| b | #2 | 2026-10-08 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapCountRules::inconsistentCounts('CLEAN.md', $lines));
    }

    public function testReportsATotalThatDoesNotEqualTheSumOfTheAreas(): void
    {
        $document = "## Summary\n\n| Area | Count |\n|------|-------|\n| Container | 2 |\n"
            . "| **Total merged PRs** | **5** |\n\n## 1. Container\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n| b | #2 | 2026-10-08 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapCountRules::inconsistentCounts('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md Summary total is 5 but the area counts sum to 2.',
            $failures[0],
        );
    }

    public function testReportsASummaryRowWithNoMatchingSection(): void
    {
        $document = "## Summary\n\n| Area | Count |\n|------|-------|\n| Widgets | 1 |\n"
            . "| **Total merged PRs** | **1** |\n\n## 1. Container\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapCountRules::inconsistentCounts('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md has a Summary row "Widgets" with no matching numbered section.',
            $failures[0],
        );
    }

    public function testCountsOnlyDataRowsNotTheHeaderOrSeparator(): void
    {
        // A section whose table has a header and a separator but NO data row
        // counts as zero; a Summary that claims one row is a failure.
        $document = "## Summary\n\n| Area | Count |\n|------|-------|\n| Container | 1 |\n"
            . "| **Total merged PRs** | **1** |\n\n## 1. Container\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n";
        $lines = explode("\n", $document);

        $failures = RoadmapCountRules::inconsistentCounts('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md Summary says "Container" = 1 but its section has 0 data row(s).',
            $failures[0],
        );
    }

    public function testIgnoresADocumentWithoutASummaryTable(): void
    {
        $document = "## 1. Container\n\n| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapCountRules::inconsistentCounts('CLEAN.md', $lines));
    }

    public function testSectionRowCountsExposesThePerSectionCounts(): void
    {
        $document = "## 1. Container\n\n| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n| b | #2 | 2026-10-08 | `e0d5a32f8` |\n\n"
            . "## 2. HTTP Layer\n\n| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| c | #3 | 2026-10-09 | `c02dad321` |\n";
        $lines = explode("\n", $document);

        self::assertSame(
            ['container' => 2, 'httplayer' => 1],
            RoadmapSectionRows::counts($lines),
        );
    }
}
