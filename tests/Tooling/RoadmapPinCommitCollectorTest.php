<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapPinCommitCollector;
use Zef\Test\Support\Tooling\RoadmapPinState;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(RoadmapPinCommitCollector::class)]
#[CoversClass(RoadmapPinState::class)]
final class RoadmapPinCommitCollectorTest extends TestCase
{
    private const string STATUS = "**Last updated:** 2026-10-17 · `main` @ `058ab8c`\n";

    #[Override]
    protected function tearDown(): void
    {
        putenv('GITHUB_BASE_REF');
        putenv('GITHUB_REF');
    }

    public function testAPullRequestLaneDoesNotJudgeThePin(): void
    {
        // GITHUB_BASE_REF marks the pull-request lanes: they validate the
        // merge ref, not main, so the pin is not theirs to judge.
        putenv('GITHUB_BASE_REF=main');

        self::assertNull($this->collector([])->state(self::STATUS));
        self::assertNull($this->collector([])->state(null));
    }

    public function testAPullRequestBaseRefOutranksTheMergeQueueRef(): void
    {
        // Defence in depth: the pull-request check comes FIRST, so a lane
        // that carries a non-empty base ref is a pull-request lane whatever
        // its GITHUB_REF says -- the queue recognition can never capture a
        // pull-request lane, and vice versa.
        $ref = 'refs/heads/gh-readonly-queue/main/pr-541-0a1b2c3d4e';
        putenv('GITHUB_BASE_REF=main');
        putenv('GITHUB_REF=' . $ref);

        self::assertNull($this->collector([])->state(self::STATUS));
    }

    public function testAMainLaneUnderAnEmptyBaseRefIsRecognised(): void
    {
        // The GitHub runner exports GITHUB_BASE_REF as an EMPTY STRING on the
        // push and workflow_dispatch events: the variable is present but
        // carries no base branch, so only a NON-EMPTY value marks a
        // pull-request lane. A presence-only check read this shape as a
        // pull-request lane and the rule never judged a single commit.
        putenv('GITHUB_BASE_REF=');
        putenv('GITHUB_REF=refs/heads/main');

        $state = $this->collector([
            'merge-base --is-ancestor' => [0, ''],
            'log --format=%s' => [0, "docs(roadmap): record the lane\n"],
        ])->state(self::STATUS);

        self::assertNotNull($state);
        self::assertSame('058ab8c', $state->pinnedSha);
        self::assertTrue($state->pinIsAncestor);
        self::assertSame(['docs(roadmap): record the lane'], $state->subjects);
    }

    public function testAMainLaneFromTheCiEnvironmentReadsTheRange(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $state = $this->collector([
            'merge-base --is-ancestor' => [0, ''],
            'log --format=%s' => [0, "docs(roadmap): pin the family\n\nchore(changelog): regenerate API changelog\n"],
        ])->state(self::STATUS);

        self::assertNotNull($state);
        self::assertSame('058ab8c', $state->pinnedSha);
        self::assertTrue($state->pinIsAncestor);
        self::assertSame(
            ['docs(roadmap): pin the family', 'chore(changelog): regenerate API changelog'],
            $state->subjects,
        );
    }

    public function testALocalCheckoutOnTheMainBranchIsRecognised(): void
    {
        $state = $this->collector([
            'rev-parse --abbrev-ref' => [0, "main\n"],
            'merge-base --is-ancestor' => [0, ''],
            'log --format=%s' => [0, "docs(roadmap): pin the family\n"],
        ])->state(self::STATUS);

        self::assertNotNull($state);
        self::assertSame('058ab8c', $state->pinnedSha);
        self::assertTrue($state->pinIsAncestor);
        self::assertSame(['docs(roadmap): pin the family'], $state->subjects);
    }

    public function testALocalCheckoutOnAFeatureBranchIsNotJudged(): void
    {
        self::assertNull(
            $this->collector(['rev-parse --abbrev-ref' => [0, "feat/some-family\n"]])->state(self::STATUS),
        );
    }

    public function testALocalCheckoutWhereGitFailsIsNotJudged(): void
    {
        // Not a repository, or git unavailable: there is no branch to judge,
        // so the lane is treated as not validating main.
        self::assertNull(
            $this->collector(['rev-parse --abbrev-ref' => [128, "fatal: not a git repository\n"]])->state(self::STATUS),
        );
    }

    public function testAMergeQueueLaneIsRecognised(): void
    {
        // The merge queue validates the group head -- the commit the queue is
        // about to make main's tip -- so the pin rule judges there too. This
        // is the lane that closes the 2026-10-22 incident class: an
        // unrecorded substantive merge now fails the GROUP's checks instead of
        // reddening main after it lands. The group-head branch name carries
        // the queued pull request's number and head sha.
        putenv('GITHUB_BASE_REF=');
        putenv('GITHUB_REF=refs/heads/gh-readonly-queue/main/pr-541-0a1b2c3d4e');

        $state = $this->collector([
            'merge-base --is-ancestor' => [0, ''],
            'log --format=%s --merges' => [0, "fix(tooling): widen the bookkeeping spellings (#532)\n"],
            'log --format=%s' => [0, "fix(tooling): widen the bookkeeping spellings (#532)\n"],
        ])->state(self::STATUS);

        self::assertNotNull($state);
        self::assertSame('058ab8c', $state->pinnedSha);
        self::assertTrue($state->pinIsAncestor);
        self::assertSame(['fix(tooling): widen the bookkeeping spellings (#532)'], $state->subjects);
        self::assertSame(['fix(tooling): widen the bookkeeping spellings (#532)'], $state->mergeSubjects);
    }

    public function testAMergeQueueLaneForAnotherBaseIsNotJudged(): void
    {
        // The queue prefix alone is not the recognition: the group must be
        // queued against MAIN. A queue lane for any other base branch is a
        // lane validating that base, not main.
        $ref = 'refs/heads/gh-readonly-queue/develop/pr-1-0a1b2c3d';
        putenv('GITHUB_BASE_REF=');
        putenv('GITHUB_REF=' . $ref);

        $checkedOut = "gh-readonly-queue/develop/pr-1-0a1b2c3d\n";

        self::assertNull(
            $this->collector(['rev-parse --abbrev-ref' => [0, $checkedOut]])->state(self::STATUS),
        );
    }

    public function testAQueueRefThatOnlyAlmostNamesMainIsNotJudged(): void
    {
        // The base segment must be exactly `main/`: a ref whose segment
        // merely starts with it (`mainly/`) is a different base, and treating
        // it as main would judge lanes whose lineage the rule cannot see.
        $ref = 'refs/heads/gh-readonly-queue/mainly/pr-1-0a1b2c3d';
        putenv('GITHUB_BASE_REF=');
        putenv('GITHUB_REF=' . $ref);

        $checkedOut = "gh-readonly-queue/mainly/pr-1-0a1b2c3d\n";

        self::assertNull(
            $this->collector(['rev-parse --abbrev-ref' => [0, $checkedOut]])->state(self::STATUS),
        );
    }

    public function testAFailingMergeLogFailsClosed(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $this->expectExceptionObject(new ToolingException(
            'Roadmap pin freshness FAILED: git could not list the merge commits after'
                . ' the pinned `058ab8c` (log exited 128).',
        ));

        $this->collector([
            'merge-base --is-ancestor' => [0, ''],
            'log --format=%s --merges' => [128, "fatal: bad object\n"],
            'log --format=%s' => [0, "docs(roadmap): record the lane\n"],
        ])->state(self::STATUS);
    }

    public function testAPinThatIsNotAnAncestorStopsBeforeTheLog(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $state = $this->collector([
            'merge-base --is-ancestor' => [1, ''],
        ])->state(self::STATUS);

        self::assertNotNull($state);
        self::assertSame('058ab8c', $state->pinnedSha);
        self::assertFalse($state->pinIsAncestor);
        self::assertSame([], $state->subjects);
    }

    public function testAnUnresolvablePinFailsClosed(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $this->expectExceptionObject(new ToolingException(
            'Roadmap pin freshness FAILED: git could not resolve the pinned commit `058ab8c` (merge-base exited 128).',
        ));

        $this->collector([
            'merge-base --is-ancestor' => [128, "fatal: Not a valid object name 058ab8c\n"],
        ])->state(self::STATUS);
    }

    public function testAFailingLogFailsClosed(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $this->expectExceptionObject(new ToolingException(
            'Roadmap pin freshness FAILED: git could not list the commits after the pinned `058ab8c` (log exited 128).',
        ));

        $this->collector([
            'merge-base --is-ancestor' => [0, ''],
            'log --format=%s' => [128, "fatal: bad object\n"],
        ])->state(self::STATUS);
    }

    public function testAMissingStatusDocumentYieldsAMissingPin(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $state = $this->collector([])->state(null);

        self::assertNotNull($state);
        self::assertNull($state->pinnedSha);
        self::assertFalse($state->pinIsAncestor);
        self::assertSame([], $state->subjects);
    }

    public function testAStatusDocumentWithoutAPinYieldsAMissingPin(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $state = $this->collector([])->state("# Roadmap Status\n\nNo pointer here.\n");

        self::assertNotNull($state);
        self::assertNull($state->pinnedSha);
        self::assertFalse($state->pinIsAncestor);
        self::assertSame([], $state->subjects);
    }

    public function testBlankLogLinesCarryNoSubject(): void
    {
        putenv('GITHUB_REF=refs/heads/main');

        $state = $this->collector([
            'merge-base --is-ancestor' => [0, ''],
            'log --format=%s' => [0, "\n\nchore(changelog): regenerate API changelog\n\n\n"],
        ])->state(self::STATUS);

        self::assertNotNull($state);
        self::assertSame('058ab8c', $state->pinnedSha);
        self::assertTrue($state->pinIsAncestor);
        self::assertSame(['chore(changelog): regenerate API changelog'], $state->subjects);
    }

    /**
     * A collector whose process runner answers only the commands the test
     * lists; anything else is an unexpected call and fails the test.
     *
     * @param array<string, array{0: int, 1: string}> $responses
     */
    private function collector(array $responses): RoadmapPinCommitCollector
    {
        $root = '/checkout-under-test';

        return new RoadmapPinCommitCollector($root, static function (string $command) use ($responses): array {
            foreach ($responses as $needle => $response) {
                if (str_contains($command, $needle)) {
                    return $response;
                }
            }

            throw new LogicException('Unexpected command: ' . $command);
        });
    }
}
