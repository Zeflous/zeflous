<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\RoadmapPinCommitCollector;
use Zef\Framework\Tooling\RoadmapPinState;
use Zef\Framework\Tooling\ToolingException;

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
