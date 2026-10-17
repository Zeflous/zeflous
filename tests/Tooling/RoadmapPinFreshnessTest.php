<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapPinFreshness;
use Zef\Framework\Tooling\RoadmapPinState;

/**
 * @internal
 */
#[CoversClass(RoadmapPinFreshness::class)]
final class RoadmapPinFreshnessTest extends TestCase
{
    public function testALaneThatIsNotValidatingMainIsNotJudged(): void
    {
        self::assertSame([], RoadmapPinFreshness::failures('ROADMAP-STATUS.md', null));
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
        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', new RoadmapPinState(null, false, []));

        self::assertCount(1, $failures);
        self::assertSame(
            'ROADMAP-STATUS.md carries no "Last updated" pin while the main lane is validating it.',
            $failures[0],
        );
    }

    public function testAPinOutsideTheMainLineageFailsClosed(): void
    {
        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', new RoadmapPinState('0badc0d', false, []));

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
            RoadmapPinFreshness::failures('ROADMAP-STATUS.md', new RoadmapPinState('5eb0ccb', true, [])),
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

        self::assertSame([], RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState));
    }

    public function testALandedChangeAfterThePinIsReportedWithItsCountAndFirstSubject(): void
    {
        $roadmapPinState = new RoadmapPinState('058ab8c', true, [
            'chore(changelog): regenerate API changelog',
            'chore(license): add the MIT license text (#409)',
            'fix(composer): exclude the vendored psr shims from the dev classmap (#410)',
        ]);

        $failures = RoadmapPinFreshness::failures('ROADMAP-STATUS.md', $roadmapPinState);

        self::assertCount(1, $failures);
        self::assertSame(
            'ROADMAP-STATUS.md "Last updated" pin `058ab8c` is stale: 2 commit(s) landed on main after it'
            . ' that are not changelog or pin bookkeeping, starting with'
            . ' "chore(license): add the MIT license text (#409)".'
            . ' Refresh the pin (CONTRIBUTING.md, rule 13).',
            $failures[0],
        );
    }

    public function testTheBookkeepingAnchorsAreExact(): void
    {
        // The three record-keeping shapes the pattern must accept, taken from
        // the real subjects the changelog and pin lanes produce.
        $accepted = [
            'chore(changelog): regenerate API changelog',
            'chore(changelog): regenerate API changelog (#408)',
            'chore(changelog-official): regenerate official changelog',
            'chore(changelog-official): regenerate official changelog (#406)',
            'docs(roadmap): pin the tags & autoconfiguration family to pull request 404',
            "Merge branch 'main' into chore/changelog-update",
        ];

        foreach ($accepted as $subject) {
            self::assertSame(
                [],
                RoadmapPinFreshness::failures('ROADMAP-STATUS.md', new RoadmapPinState('058ab8c', true, [$subject])),
                $subject,
            );
        }
    }

    public function testNearMissesOfTheBookkeepingAnchorsAreRefused(): void
    {
        // One character off any anchor and the steady state turns red: a
        // mutated pattern must not swallow a real change.
        $refused = [
            'chore(changelog): regenerate API changelogs',
            'chore(changelog): regenerate the API changelog',
            'chore(changelog): regenerate API changelog hand-edited',
            'chore(changelog-api): regenerate API changelog',
            'docs(roadmap): pinned the tags family to pull request 404',
            'docs(roadmap): unpin the tags family',
            "Merge branch 'dev' into chore/changelog-update",
            "Merge branch 'main' into feat/container-tags-autoconfig-family",
            'feat(container): the tags & autoconfiguration family, one tag one source (#404)',
            'chore(license): add the MIT license text (#409)',
        ];

        foreach ($refused as $subject) {
            $failures = RoadmapPinFreshness::failures(
                'ROADMAP-STATUS.md',
                new RoadmapPinState('058ab8c', true, [$subject]),
            );

            self::assertCount(1, $failures, $subject);
            self::assertStringContainsString('is stale: 1 commit(s) landed on main', $failures[0], $subject);
        }
    }
}
