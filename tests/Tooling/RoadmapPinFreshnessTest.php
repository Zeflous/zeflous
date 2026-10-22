<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapPinFreshness;
use Zef\Test\Support\Tooling\RoadmapPinState;

/**
 * @internal
 */
#[CoversClass(RoadmapPinFreshness::class)]
final class RoadmapPinFreshnessTest extends TestCase
{
    public function testALaneThatIsNotValidatingMainIsNotJudged(): void
    {
        self::assertSame([], RoadmapPinFreshness::failures('ROADMAP-STATUS.md', null, []));
    }

    public function testThePassSuffixSaysWhetherThePinWasJudged(): void
    {
        self::assertSame(
            ' (pin freshness not judged: the lane is not validating main).',
            RoadmapPinFreshness::suffix(null),
        );
        self::assertSame(
            ' (pin freshness verified on main).',
            RoadmapPinFreshness::suffix(new RoadmapPinState('5eb0ccb', true, [])),
        );
    }

    public function testAMissingPinOnMainFailsClosed(): void
    {
        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', new RoadmapPinState(null, false, []), []);

        self::assertCount(1, $failures);
        self::assertSame(
            'ROADMAP-STATUS.md carries no "Last updated" pin while the main lane is validating it.',
            $failures[0],
        );
    }

    public function testAPinOutsideTheMainLineageFailsClosed(): void
    {
        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', new RoadmapPinState('0badc0d', false, []), []);

        self::assertCount(1, $failures);
        self::assertSame(
            'ROADMAP-STATUS.md "Last updated" pin `0badc0d` is not an ancestor of the validated commit:'
            . ' it does not name a commit of the main lineage.',
            $failures[0],
        );
    }

    public function testAnEmptyRangeAfterThePinIsFresh(): void
    {
        self::assertSame(
            [],
            RoadmapPinFreshness::failures('ROADMAP-STATUS.md', new RoadmapPinState('5eb0ccb', true, []), []),
        );
    }

    public function testOnlyBookkeepingAfterThePinPasses(): void
    {
        // The steady state on main after a pin refresh: the refresh commits
        // themselves (branch subject and merge-commit subject), the changelog
        // regenerations that followed, and the changelog lane's update-branch
        // merge. Measured from the real 058ab8c..5eb0ccb range.
        $roadmapPinState = new RoadmapPinState('058ab8c', true, [
            'docs(roadmap): pin the tags & autoconfiguration family to pull request 404',
            'docs(roadmap): pin the tags & autoconfiguration family to pull request 404 (#405)',
            'chore(changelog): regenerate API changelog',
            'chore(changelog): regenerate API changelog (#407)',
            'chore(changelog-official): regenerate official changelog (#406)',
            "Merge branch 'main' into chore/changelog-update",
        ]);

        self::assertSame([], RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, []));
    }

    public function testTheAutomationUpdateMergeSpellingsKeepAStalePinGreen(): void
    {
        // The 2026-10-22 incident: the machinery that merges `main` into the
        // branches it refreshes also spells its merges with the web UI's
        // update button, a shorthand, and a descriptive custom subject. A
        // pin that only has such merges above it is still fresh -- they
        // reflect main's own commits back into branches, and the real content
        // those branches carried reached main through pull-request merge
        // commits the patterns refuse. Measured from the real subjects of
        // that day's history.
        $roadmapPinState = new RoadmapPinState('5eb0ccb', true, [
            "Merge remote-tracking branch 'origin/main' into fix/edge-case-audit",
            'chore: merge main (the audit remediation batch #518) into the router cache pin',
            "Merge remote-tracking branch 'origin/main' into feat/router-host-wildcard",
            'chore: merge main (changelog bookkeeping #520/#521) into the router cache lane',
            'Merge origin/main into fix/changelog-api-workflows',
        ]);

        self::assertSame([], RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, []));
    }

    public function testALandedChangeAfterThePinIsReportedWithItsCountAndFirstSubject(): void
    {
        $roadmapPinState = new RoadmapPinState('058ab8c', true, [
            'chore(changelog): regenerate API changelog',
            'chore(license): add the MIT license text (#409)',
            'fix(composer): exclude the vendored psr shims from the dev classmap (#410)',
        ]);

        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, []);

        self::assertCount(1, $failures);
        self::assertSame(
            'ROADMAP-STATUS.md "Last updated" pin `058ab8c` is stale: 2 commit(s) landed on main after it'
            . ' that are not changelog, roadmap bookkeeping, or a recorded pull-request merge, starting with'
            . ' "chore(license): add the MIT license text (#409)".'
            . ' Refresh the pin (CONTRIBUTING.md, rule 13).',
            $failures[0],
        );
    }

    public function testTheBookkeepingAnchorsAreExact(): void
    {
        // The three record-keeping shapes the pattern must accept, taken from
        // the real subjects the changelog and roadmap lanes and the
        // branch-update machinery produce -- the roadmap lanes' full verb
        // vocabulary (pin, record, point, refresh, keep) observed in this
        // repository's own history, and the update merges arrive for every
        // branch kind, each one observed in this repository's own history.
        // The update machinery's own merge spelling (its git invocation) plus
        // the three further spellings observed on 2026-10-22 -- the web UI's
        // update button, the shorthand, and the descriptive custom subject --
        // each measured from this repository's own history.
        $accepted = [
            'chore(changelog): regenerate API changelog',
            'chore(changelog): regenerate API changelog (#408)',
            'chore(changelog-official): regenerate official changelog',
            'chore(changelog-official): regenerate official changelog (#406)',
            'docs(roadmap): pin the tags & autoconfiguration family to pull request 404',
            'docs(roadmap): record merged error handling verification (#488)',
            'docs(roadmap): point the history pin at the #444 merge commit',
            'docs(roadmap): refresh the status meter for the radix lane (#482)',
            'docs(roadmap): keep the history pointer current',
            "Merge branch 'main' into chore/changelog-update",
            "Merge branch 'main' into docs/roadmap-engineering-pin",
            "Merge branch 'main' into feat/tooling-roadmap-pin-freshness",
            "Merge branch 'main' into test/covers-orphan-classes",
            "Merge branch 'main' into fix/composer-psr-classmap-excludes",
            "Merge remote-tracking branch 'origin/main' into feat/router-host-wildcard",
            "Merge remote-tracking branch 'origin/main' into fix/edge-case-audit",
            'Merge origin/main into fix/changelog-api-workflows',
            'chore: merge main (the audit remediation batch #518) into the router cache pin',
            'chore: merge main (changelog bookkeeping #520/#521) into the router cache lane',
        ];

        foreach ($accepted as $subject) {
            $roadmapPinState = new RoadmapPinState('058ab8c', true, [$subject]);

            self::assertSame(
                [],
                RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, []),
                $subject,
            );
        }
    }

    public function testNearMissesOfTheBookkeepingAnchorsAreRefused(): void
    {
        // One character off any anchor and the steady state turns red: a
        // mutated pattern must not swallow a real change. The roadmap
        // anchor's boundary is the scope-and-colon head itself: anything
        // that is not exactly `docs(roadmap): ` followed by a subject is a
        // landed change. The update-merge spellings keep their boundaries
        // the same way: a merge whose source is not `main`, a custom
        // subject without its parenthetical, or a prefix that is not
        // `chore:` is a landed change.
        $refused = [
            'chore(changelog): regenerate API changelogs',
            'chore(changelog): regenerate the API changelog',
            'chore(changelog): regenerate API changelog hand-edited',
            'chore(changelog-api): regenerate API changelog',
            'docs (roadmap): record the lane',
            'docs(roadmap):record the lane',
            'docs(roadmaps): record the lane',
            'Docs(roadmap): record the lane',
            "Merge branch 'dev' into chore/changelog-update",
            "Merge branch 'feat/container' into main",
            'Merge pull request #417 from Zeflous/feat/tooling-roadmap-pin-freshness',
            'feat(container): the tags & autoconfiguration family, one tag one source (#404)',
            'chore(license): add the MIT license text (#409)',
            "Merge remote-tracking branch 'origin/dev' into feat/router-host-wildcard",
            "Merge branch 'origin/main' into feat/router-host-wildcard",
            'Merge origin/dev into fix/changelog-api-workflows',
            'chore: merge main into the router cache lane',
            'chore: merge main (the audit remediation batch #518) from the router cache pin',
            'chore: merge dev (the audit remediation batch #518) into the router cache pin',
            'fix: merge main (hotfix) into production',
        ];

        foreach ($refused as $subject) {
            $failures = RoadmapPinFreshness::failures(
                'ROADMAP-STATUS.md',
                new RoadmapPinState('058ab8c', true, [$subject]),
                [],
            );

            self::assertCount(1, $failures, $subject);
            self::assertStringContainsString('is stale: 1 commit(s) landed on main', $failures[0], $subject);
        }
    }

    public function testARecordedPullRequestMergeLandingKeepsThePinFresh(): void
    {
        // The 2026-10-22 incident's remedy at the mechanism level: the merge
        // machinery lands a pull request on main as a merge commit stamped
        // `<title> (#<number>)`, and when the tree under validation already
        // carries that number's ROADMAP-HISTORY row, the landing is accounted
        // for -- the pin the pull request itself refreshed stays fresh across
        // its own landing. Measured from the real #532 landing subject.
        $roadmapPinState = new RoadmapPinState(
            '1d27b6c',
            true,
            ['fix(tooling): accept every observed update-merge spelling as bookkeeping (#532)'],
            ['fix(tooling): accept every observed update-merge spelling as bookkeeping (#532)'],
        );

        self::assertSame([], RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, [532]));
    }

    public function testAnUnrecordedPullRequestMergeLandingLeavesThePinStale(): void
    {
        // The queue's own discipline: a landing whose pull request carries no
        // ROADMAP-HISTORY row in the tree under validation is an unaccounted
        // substantive change -- exactly the #537 incident, whose healing pull
        // request chased the merge and reddened main in between.
        $roadmapPinState = new RoadmapPinState(
            '6f8fecf',
            true,
            ['docs(contributing): require error-system and configuration-system integration (#537)'],
            ['docs(contributing): require error-system and configuration-system integration (#537)'],
        );

        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, [532]);

        self::assertCount(1, $failures);
        self::assertStringContainsString('is stale: 1 commit(s) landed on main', $failures[0]);
        self::assertStringContainsString(
            'not changelog, roadmap bookkeeping, or a recorded pull-request merge',
            $failures[0],
        );
    }

    public function testASuffixSpelledCommitThatIsNotAMergeDoesNotQualify(): void
    {
        // Only the merge machinery stamps the trailing number: a plain commit
        // spelled with the suffix must not piggyback on another pull
        // request's row.
        $roadmapPinState = new RoadmapPinState(
            '1d27b6c',
            true,
            ['fix(tooling): smuggle a change past the pin (#532)'],
            [],
        );

        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, [532]);

        self::assertCount(1, $failures);
    }

    public function testAMergeCommitWithoutTheTrailingNumberDoesNotQualify(): void
    {
        // A merge commit whose subject carries no trailing "(#<number>)" is
        // not the machinery's landing shape and must be refused whatever the
        // ledger says.
        $roadmapPinState = new RoadmapPinState(
            '1d27b6c',
            true,
            ['Merge pull request 532 into main without the stamped number'],
            ['Merge pull request 532 into main without the stamped number'],
        );

        self::assertCount(1, RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, [532]));
    }

    public function testTheRecordedLandingNearMissesAreRefused(): void
    {
        // One character off the landing shape and the walk turns red: the
        // number without its parentheses, the suffix not at the end of the
        // subject, a neighbour's number, and the zero a numberless row would
        // contribute -- each is an unaccounted change.
        $mergeSubjects = [
            'fix(tooling): widen the bookkeeping spellings #532',
            'fix(tooling): widen the bookkeeping spellings (#532) follow-up',
            'fix(tooling): widen the bookkeeping spellings (#533)',
            'fix(tooling): widen the bookkeeping spellings (#0)',
        ];

        foreach ($mergeSubjects as $mergeSubject) {
            $failures = RoadmapPinFreshness::failures(
                'ROADMAP-STATUS.md',
                new RoadmapPinState('1d27b6c', true, [$mergeSubject], [$mergeSubject]),
                [532],
            );

            self::assertCount(1, $failures, $mergeSubject);
        }
    }

    public function testASubjectAfterARecordedLandingIsStillJudged(): void
    {
        // A recorded landing is not the end of the walk: a subject that
        // lands after it is still judged, and a loop that stopped at the
        // recorded landing would hide every later landed change.
        $roadmapPinState = new RoadmapPinState(
            '1d27b6c',
            true,
            [
                'fix(tooling): accept every observed update-merge spelling as bookkeeping (#532)',
                'feat(router): an unrecorded change past a recorded landing',
            ],
            ['fix(tooling): accept every observed update-merge spelling as bookkeeping (#532)'],
        );

        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, [532]);

        self::assertCount(1, $failures);
        self::assertStringContainsString(
            'starting with "feat(router): an unrecorded change past a recorded landing"',
            $failures[0],
        );
    }

    public function testALedgerlessWalkRefusesEveryMergeShapedSubject(): void
    {
        // Fail closed on the ledger axis: a walk whose history document
        // records no pull requests at all refuses even a perfectly shaped
        // landing commit.
        $subject = 'fix(tooling): accept every observed update-merge spelling as bookkeeping (#532)';
        $roadmapPinState = new RoadmapPinState('1d27b6c', true, [$subject], [$subject]);

        self::assertCount(1, RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, []));
    }

    public function testTheBookkeepingPatternsWinWithoutAnyLedgerRow(): void
    {
        // Pattern precedence: a changelog regeneration stamped with the
        // pull-request suffix is bookkeeping by its pattern alone -- no
        // history row required -- and a docs(roadmap) landing likewise.
        $roadmapPinState = new RoadmapPinState(
            '1d27b6c',
            true,
            [
                'chore(changelog): regenerate API changelog (#539)',
                'docs(roadmap): heal the status pin over the contributing integration rule (#541)',
            ],
            [
                'chore(changelog): regenerate API changelog (#539)',
                'docs(roadmap): heal the status pin over the contributing integration rule (#541)',
            ],
        );

        self::assertSame([], RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState, []));
    }
}
