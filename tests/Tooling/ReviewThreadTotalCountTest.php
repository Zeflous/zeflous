<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\ReviewThreadTotalCount;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(ReviewThreadTotalCount::class)]
final class ReviewThreadTotalCountTest extends TestCase
{
    public function testReadsTheConnectionTotal(): void
    {
        $reviewThreadTotalCount = new ReviewThreadTotalCount($this->payload(['totalCount' => 7]));

        self::assertSame(7, $reviewThreadTotalCount->value());
    }

    public function testZeroIsAnHonestTotal(): void
    {
        $reviewThreadTotalCount = new ReviewThreadTotalCount($this->payload(['totalCount' => 0]));

        self::assertSame(0, $reviewThreadTotalCount->value());
    }

    /**
     * A payload that does not decode to a map is a refusal, never a zero:
     * a total the resolver cannot read is a total it cannot cross-check.
     */
    public function testRefusesPayloadThatIsNotJson(): void
    {
        try {
            new ReviewThreadTotalCount('not json')->value();
            self::fail('A payload that is not JSON must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread payload is missing reviewThreads.totalCount.',
                $toolingException->getMessage(),
            );
        }
    }

    public function testRefusesPayloadWithoutTheTotal(): void
    {
        try {
            new ReviewThreadTotalCount($this->payload([]))->value();
            self::fail('A payload without the total must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread payload is missing reviewThreads.totalCount.',
                $toolingException->getMessage(),
            );
        }
    }

    /**
     * A string total is not the integer shape the cross-check compares
     * against -- a JSON payload that spells the number in quotes is a
     * payload the walk cannot trust.
     */
    public function testRefusesATotalThatIsNotAnInteger(): void
    {
        try {
            new ReviewThreadTotalCount($this->payload(['totalCount' => '7']))->value();
            self::fail('A non-integer total must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread payload is missing reviewThreads.totalCount.',
                $toolingException->getMessage(),
            );
        }
    }

    /**
     * The path walk refuses at its first non-map level: a `data` section
     * that is a scalar answers no repository, no pull request, no
     * connection -- and no total.
     */
    public function testRefusesPayloadWhosePathIsNotWalkable(): void
    {
        try {
            new ReviewThreadTotalCount((string) json_encode(['data' => 'unavailable']))->value();
            self::fail('An unwalkable payload path must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread payload is missing reviewThreads.totalCount.',
                $toolingException->getMessage(),
            );
        }
    }

    /**
     * @param array<string, mixed> $reviewThreads
     */
    private function payload(array $reviewThreads): string
    {
        return (string) json_encode([
            'data' => [
                'repository' => [
                    'pullRequest' => [
                        'reviewThreads' => $reviewThreads,
                    ],
                ],
            ],
        ]);
    }
}
