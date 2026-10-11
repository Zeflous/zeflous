<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapTableRules;

/**
 * @internal
 */
#[CoversClass(RoadmapTableRules::class)]
final class RoadmapTableRulesTest extends TestCase
{
    public function testRaggedBlocksReportsEveryRaggedTable(): void
    {
        $lines = explode("\n", "| A | B |\n|---|---|\n| 1 |\n\n| C | D |\n|---|---|\n| 2 |\n");

        $failures = RoadmapTableRules::raggedBlocks('BROKEN.md', $lines);

        self::assertCount(2, $failures);
        self::assertSame(
            'BROKEN.md:4 ends a table whose rows do not all have the same column count.',
            $failures[0],
        );
        self::assertSame(
            'BROKEN.md:8 ends a table whose rows do not all have the same column count.',
            $failures[1],
        );
    }

    public function testRaggedBlocksPassesOnAUniformTable(): void
    {
        $lines = explode("\n", "| A | B |\n|---|---|\n| 1 | 2 |\n");

        self::assertSame([], RoadmapTableRules::raggedBlocks('CLEAN.md', $lines));
    }

    public function testRaggedBlocksReportsATableThatReachesTheLastLine(): void
    {
        // The table is the very last thing in the document: the appended
        // sentinel line is the only thing that flushes and reports it.
        $lines = explode("\n", "| A | B |\n|---|---|\n| 1 |\n");

        $failures = RoadmapTableRules::raggedBlocks('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md:4 ends a table whose rows do not all have the same column count.',
            $failures[0],
        );
    }

    public function testGluedHeadingsReportsEveryGluedHeading(): void
    {
        $document = "| A | B |\n|---|---|\n| 1 | 2 |\n## Next\n\n| C | D |\n|---|---|\n| 3 | 4 |\n## Later\n";
        $lines = explode("\n", $document);

        $failures = RoadmapTableRules::gluedHeadings('BROKEN.md', $lines);

        self::assertCount(2, $failures);
        self::assertSame(
            'BROKEN.md:4 glues a heading under a table row (insert a blank line).',
            $failures[0],
        );
        self::assertSame(
            'BROKEN.md:9 glues a heading under a table row (insert a blank line).',
            $failures[1],
        );
    }

    public function testGluedHeadingsReportsAHeadingOnTheSecondLine(): void
    {
        // The heading sits at index 1, directly under a table row at index 0 --
        // the boundary case of "look at the previous line".
        $lines = explode("\n", "| A | B |\n## Next\n");

        $failures = RoadmapTableRules::gluedHeadings('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md:2 glues a heading under a table row (insert a blank line).',
            $failures[0],
        );
    }

    public function testGluedHeadingsIgnoresAHashMidLine(): void
    {
        // Only a line that STARTS with `#` is a heading; the `# ` is mid-line.
        $lines = explode("\n", "| A | B |\n|---|---|\n| 1 | 2 |\nnote # 1\n");

        self::assertSame([], RoadmapTableRules::gluedHeadings('CLEAN.md', $lines));
    }

    public function testGluedHeadingsIgnoresTheFirstLine(): void
    {
        // A heading on the very first line has no preceding row to glue to.
        $lines = explode("\n", "# Title\n");

        self::assertSame([], RoadmapTableRules::gluedHeadings('CLEAN.md', $lines));
    }
}
