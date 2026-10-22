<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapRecordedMerge;

/**
 * @internal
 */
#[CoversClass(RoadmapRecordedMerge::class)]
final class RoadmapRecordedMergeTest extends TestCase
{
    private const string LANDING = 'fix(tooling): accept every observed update-merge spelling as bookkeeping (#532)';

    public function testAMergeSubjectOfARecordedPullRequestIsAccepted(): void
    {
        // The machinery's landing shape, measured from the real #532 landing:
        // a merge commit of the walk whose trailing "(#532)" carries a
        // ROADMAP-HISTORY row in the tree under validation.
        self::assertTrue(RoadmapRecordedMerge::isAccepted(self::LANDING, [self::LANDING], [532]));
    }

    public function testASubjectThatIsNotAmongTheMergeCommitsIsRefused(): void
    {
        // Only the machinery stamps the number: a plain commit spelled with
        // the suffix must not piggyback on a recorded pull request's row.
        self::assertFalse(RoadmapRecordedMerge::isAccepted(self::LANDING, [], [532]));
    }

    public function testASubjectWithoutTheTrailingNumberIsRefused(): void
    {
        $subject = 'fix(tooling): accept every observed update-merge spelling as bookkeeping #532';

        self::assertFalse(RoadmapRecordedMerge::isAccepted($subject, [$subject], [532]));
    }

    public function testASubjectWhoseNumberCarriesNoHistoryRowIsRefused(): void
    {
        // The 2026-10-22 incident, compressed into one call: the #537 landing
        // subject under a ledger that records no row for it.
        $subject = 'docs(contributing): require error-system and configuration-system integration (#537)';

        self::assertFalse(RoadmapRecordedMerge::isAccepted($subject, [$subject], [532]));
    }

    public function testAnEmptyLedgerRefusesEveryLanding(): void
    {
        self::assertFalse(RoadmapRecordedMerge::isAccepted(self::LANDING, [self::LANDING], []));
    }

    public function testTheZeroASuffixSpellsIsNotAListIndex(): void
    {
        // The membership is a key lookup over the FLIPPED ledger, not a
        // position lookup over the list: "(#0)" must not resolve to the
        // list's own first index. A lookup that fell back to the list would
        // accept it (index 0 exists) -- this test pins that it refuses.
        $subject = 'fix(tooling): widen the bookkeeping spellings (#0)';

        self::assertFalse(RoadmapRecordedMerge::isAccepted($subject, [$subject], [532, 533]));
    }

    public function testARecordedNumberThatIsAlsoAListPositionIsAcceptedOnlyAsAKey(): void
    {
        // The mirror of the zero test: number 533 is BOTH a member of the
        // ledger and (coincidentally) an index of the list -- acceptance must
        // come from the key, so removing the member while keeping the index
        // occupied by another number must refuse.
        $subject = 'fix(tooling): widen the bookkeeping spellings (#533)';

        self::assertTrue(RoadmapRecordedMerge::isAccepted($subject, [$subject], [532, 533]));
        self::assertFalse(RoadmapRecordedMerge::isAccepted($subject, [$subject], [532, 700]));
    }

    public function testTheSuffixMustEndTheSubject(): void
    {
        $subject = 'fix(tooling): widen the bookkeeping spellings (#532) follow-up';

        self::assertFalse(RoadmapRecordedMerge::isAccepted($subject, [$subject], [532]));
    }
}
