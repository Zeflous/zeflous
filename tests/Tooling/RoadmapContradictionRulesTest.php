<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapContradictionRules;
use Zef\Test\Support\Tooling\RoadmapSectionRows;
use Zef\Test\Support\Tooling\RoadmapTableCells;

/**
 * @internal
 */
#[CoversClass(RoadmapContradictionRules::class)]
#[CoversClass(RoadmapSectionRows::class)]
#[CoversClass(RoadmapTableCells::class)]
final class RoadmapContradictionRulesTest extends TestCase
{
    public function testReportsAnUnstartedAreaThatHasMergedRows(): void
    {
        // The shipped defect: the `## Not yet done` bullet declared the HTTP
        // Layer "not started" while the same file's numbered section already
        // carried merged rows. The structural rules accept it; this rule must
        // not.
        $document = "## Not yet done\n\n"
            . "- **HTTP Layer (Zero-Library Shim)** — not started beyond the PSR container shim.\n\n"
            . "## 1. HTTP Layer\n\n| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #256 | 2026-10-11 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapContradictionRules::unstartedAreasWithMergedWork('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md:3 declares "HTTP Layer (Zero-Library Shim)" not started, but its section has 1 merged row(s).',
            $failures[0],
        );
    }

    public function testPassesWhenTheAreaHasNoMergedRows(): void
    {
        $document = "## Not yet done\n\n"
            . "- **HTTP Layer (Zero-Library Shim)** — not started beyond the PSR container shim.\n\n"
            . "## 1. HTTP Layer\n\n| Work item | PR | Merged | Commit |\n|---|---|---|---|\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }

    public function testPassesWhenTheClaimListsUnstartedSubFeatures(): void
    {
        // A bullet that merely lists unstarted sub-features is not a whole-area
        // "not started" claim and must be left alone.
        $document = "## Not yet done\n\n"
            . '- **HTTP Layer (Zero-Library Shim)** — handler chain, debug/production modes,'
            . " observability not started.\n\n"
            . "## 1. HTTP Layer\n\n| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #256 | 2026-10-11 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }

    public function testIgnoresBulletsOutsideTheNotYetDoneSection(): void
    {
        $document = "## 1. HTTP Layer\n\n"
            . "- **HTTP Layer (Zero-Library Shim)** — not started beyond the PSR container shim.\n\n"
            . "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #256 | 2026-10-11 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }

    public function testIgnoresAnAreaWithNoMatchingSection(): void
    {
        $document = "## Not yet done\n\n"
            . "- **Widgets** — not started.\n\n"
            . "## 1. HTTP Layer\n\n| Work item | PR | Merged | Commit |\n|---|---|---|---|\n"
            . "| a | #256 | 2026-10-11 | `e0d5a32f8` |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapContradictionRules::unstartedAreasWithMergedWork('CLEAN.md', $lines));
    }
}
