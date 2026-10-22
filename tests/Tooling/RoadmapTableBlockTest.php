<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapTableBlock;

/**
 * @internal
 */
#[CoversClass(RoadmapTableBlock::class)]
final class RoadmapTableBlockTest extends TestCase
{
    public function testCountsTheDataRowsAfterTheSeparator(): void
    {
        $table = [
            '| Area | Status | Evidence |',
            '|---|---|---|',
            '| 1 | done | a |',
            '| 2 | done | b |',
            '| 3 | open | c |',
        ];

        self::assertSame(3, RoadmapTableBlock::dataRows($table));
    }

    public function testABlockWithoutASeparatorHasNoDataRows(): void
    {
        $table = [
            '| Area | Status |',
            '| 1 | done |',
            '| 2 | done |',
        ];

        self::assertSame(0, RoadmapTableBlock::dataRows($table));
    }

    public function testAnEmptyBlockHasNoDataRows(): void
    {
        self::assertSame(0, RoadmapTableBlock::dataRows([]));
    }

    public function testTheFirstSeparatorOwnsTheCount(): void
    {
        // Two separator rows in one collected block: the first one is the
        // block's own, everything after it counts as data -- including the
        // stray second separator, which is exactly why the rule exists.
        $table = [
            '| Area |',
            '|---|',
            '| 1 |',
            '|---|',
            '| 2 |',
        ];

        self::assertSame(3, RoadmapTableBlock::dataRows($table));
    }

    public function testASeparatorLeadingTheBlockCountsEverythingAfterIt(): void
    {
        $table = [
            '|---|---|',
            '| 1 | done |',
            '| 2 | done |',
        ];

        self::assertSame(2, RoadmapTableBlock::dataRows($table));
    }

    public function testBothSeparatorAlignmentsAreRecognised(): void
    {
        $left = ['| Area |', '|:---|', '| 1 |'];
        $right = ['| Area |', '|---:|', '| 1 |'];

        self::assertSame(1, RoadmapTableBlock::dataRows($left));
        self::assertSame(1, RoadmapTableBlock::dataRows($right));
    }

    public function testASingleDataRowIsCounted(): void
    {
        $table = [
            '| Area |',
            '|---|',
            '| 1 |',
        ];

        self::assertSame(1, RoadmapTableBlock::dataRows($table));
    }
}
