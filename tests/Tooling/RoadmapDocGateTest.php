<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapDocGate;
use Zef\Test\Support\Tooling\RoadmapDocReport;
use Zef\Test\Support\Tooling\RoadmapPinState;

/**
 * @internal
 */
#[CoversClass(RoadmapDocGate::class)]
final class RoadmapDocGateTest extends TestCase
{
    public function testPassesOnACleanDocument(): void
    {
        $document = "# Title\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n## Next\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['CLEAN.md' => $document]));

        self::assertTrue($gateResult->passed);
        self::assertSame(
            'Roadmap document gate PASSED: 1 document(s) audited'
                . ' (pin freshness not judged: the lane is not validating main).',
            $gateResult->message,
        );
    }

    public function testPassesWithAVerifiedPinOnMain(): void
    {
        $document = "# Title\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n## Next\n";

        $gateResult = new RoadmapDocGate()->evaluate(
            new RoadmapDocReport(['CLEAN.md' => $document]),
            new RoadmapPinState('5eb0ccb', true, []),
        );

        self::assertTrue($gateResult->passed);
        self::assertSame(
            'Roadmap document gate PASSED: 1 document(s) audited (pin freshness verified on main).',
            $gateResult->message,
        );
    }

    public function testFailsOnAStalePinOnMain(): void
    {
        $document = "# Title\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n## Next\n";

        $gateResult = new RoadmapDocGate()->evaluate(
            new RoadmapDocReport(['CLEAN.md' => $document]),
            new RoadmapPinState('058ab8c', true, ['chore(license): add the MIT license text (#409)']),
        );

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('pin `058ab8c` is stale: 1 commit(s) landed on main', $gateResult->message);
    }

    public function testFailsOnAnUnclosedTableRow(): void
    {
        // The shipped defect: the `---` separator was appended to the data row
        // instead of standing on its own line, so the row never closed. Two
        // broken rows in ONE document, so the failure list carries more than
        // one entry (a single-entry list is indistinguishable from one that
        // dropped its tail, and that is exactly the mutant to catch).
        $document = "| A | B |\n|---|---|\n| 1 | 2 |---\n| 3 | 4 |---\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['BROKEN.md' => $document]));

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'BROKEN.md:3 has an unclosed table row (a table row must end with "|").',
            $gateResult->message,
        );
        self::assertStringContainsString(
            'BROKEN.md:4 has an unclosed table row (a table row must end with "|").',
            $gateResult->message,
        );
    }

    public function testFailsOnAnUnclosedRowAfterNonTableLines(): void
    {
        // A non-table line precedes the broken row. The scan must continue past
        // it rather than stopping, otherwise the broken row is never reached.
        $document = "# Title\n\n| A | B |\n|---|---|\n| 1 | 2 |---\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['BROKEN.md' => $document]));

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'BROKEN.md:5 has an unclosed table row (a table row must end with "|").',
            $gateResult->message,
        );
    }

    public function testFailsOnARaggedTableBlock(): void
    {
        // Two ragged tables in ONE document (each separated by a blank line),
        // so the failure list carries more than one entry.
        $document = "| A | B |\n|---|---|\n| 1 |\n\n| C | D |\n|---|---|\n| 2 |\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['BROKEN.md' => $document]));

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'BROKEN.md:4 ends a table whose rows do not all have the same column count.',
            $gateResult->message,
        );
        self::assertStringContainsString(
            'BROKEN.md:8 ends a table whose rows do not all have the same column count.',
            $gateResult->message,
        );
    }

    public function testFailsOnAHeadingGluedUnderATableRow(): void
    {
        // Two glued headings in ONE document, so the failure list carries more
        // than one entry.
        $document = "| A | B |\n|---|---|\n| 1 | 2 |\n## Next\n\n| C | D |\n|---|---|\n| 3 | 4 |\n## Later\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['BROKEN.md' => $document]));

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'BROKEN.md:4 glues a heading under a table row (insert a blank line).',
            $gateResult->message,
        );
        self::assertStringContainsString(
            'BROKEN.md:9 glues a heading under a table row (insert a blank line).',
            $gateResult->message,
        );
    }

    public function testDoesNotTreatAHashInOrdinaryTextAsAHeading(): void
    {
        // Only a line that STARTS with `#` is a heading. The line below sits
        // directly under a table row and contains a `# ` mid-sentence; without
        // the start anchor it would be mistaken for a glued heading and this
        // clean document would fail.
        $document = "| A | B |\n|---|---|\n| 1 | 2 |\nnote # 1\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['CLEAN.md' => $document]));

        self::assertTrue($gateResult->passed);
    }

    public function testAuditsEveryDocumentInTheReport(): void
    {
        $roadmapDocReport = new RoadmapDocReport([
            'A.md' => "| x |\n|---|\n| 1 |---\n",
            'B.md' => "| y |\n|---|\n| 2 |---\n",
        ]);

        $gateResult = new RoadmapDocGate()->evaluate($roadmapDocReport);

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString('A.md:3', $gateResult->message);
        self::assertStringContainsString('B.md:3', $gateResult->message);
    }

    public function testFailsOnAPlaceholderCommitCell(): void
    {
        // The shipped defect: a literal `PENDING` placeholder in the Commit
        // column. The structural rules accept it, so the gate must catch it
        // through the commit-cell rule.
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n| a | #1 | 2026-10-07 | `PENDING` |\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['BROKEN.md' => $document]));

        self::assertFalse($gateResult->passed);
        self::assertStringContainsString(
            'BROKEN.md:3 has a Commit cell that is not a merge SHA ("`PENDING`").',
            $gateResult->message,
        );
    }

    public function testPassesOnARealCommitCell(): void
    {
        $document = "| Work item | PR | Merged | Commit |\n|---|---|---|---|\n| a | #1 | 2026-10-07 | `f6aa0dbeb8` |\n";

        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport(['CLEAN.md' => $document]));

        self::assertTrue($gateResult->passed);
    }

    public function testPassesOnAnEmptyReport(): void
    {
        $gateResult = new RoadmapDocGate()->evaluate(new RoadmapDocReport([]));

        self::assertTrue($gateResult->passed);
        self::assertSame(
            'Roadmap document gate PASSED: 0 document(s) audited'
                . ' (pin freshness not judged: the lane is not validating main).',
            $gateResult->message,
        );
    }
}
