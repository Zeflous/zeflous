<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ReviewThreadResolver;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(ReviewThreadResolver::class)]
final class ReviewThreadResolverTest extends TestCase
{
    public function testResolvesOnlyFinishedConversations(): void
    {
        $calls = [];

        $graphql = static function (string $query, array $variables) use (&$calls): string {
            $calls[] = $variables;

            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_live', 'isResolved' => false, 'isOutdated' => false, 'path' => 'src/A.php'],
                    ['id' => 'PRRT_done', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                    ['id' => 'PRRT_already', 'isResolved' => true, 'isOutdated' => true, 'path' => 'src/C.php'],
                ]);
            }

            $threadId = $variables['threadId'] ?? '';

            return self::resolveSuccess(\is_string($threadId) ? $threadId : '');
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(131);

        self::assertSame(3, $reviewThreadResolution->total);
        self::assertSame(1, $reviewThreadResolution->resolved);
        self::assertSame(0, $reviewThreadResolution->failed);
        // One query + exactly one mutation, for the single finished thread.
        self::assertCount(2, $calls);
        self::assertSame(131, $calls[0]['number']);
        self::assertSame('PRRT_done', $calls[1]['threadId']);
    }

    public function testResolvesEveryFinishedConversationInOrder(): void
    {
        $mutations = [];

        $graphql = static function (string $query, array $variables) use (&$mutations): string {
            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_live', 'isResolved' => false, 'isOutdated' => false, 'path' => 'src/A.php'],
                    ['id' => 'PRRT_one', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                    ['id' => 'PRRT_two', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/C.php'],
                    ['id' => 'PRRT_three', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/D.php'],
                ]);
            }

            $threadId = $variables['threadId'] ?? '';
            $mutations[] = \is_string($threadId) ? $threadId : '';

            return self::resolveSuccess(\is_string($threadId) ? $threadId : '');
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(12);

        self::assertSame(4, $reviewThreadResolution->total);
        self::assertSame(3, $reviewThreadResolution->resolved);
        self::assertSame(0, $reviewThreadResolution->failed);
        self::assertSame(['PRRT_one', 'PRRT_two', 'PRRT_three'], $mutations);
    }

    public function testKeepsResolvingAfterAFailedMutation(): void
    {
        $mutations = [];

        $graphql = static function (string $query, array $variables) use (&$mutations): string {
            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_first', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                    ['id' => 'PRRT_second', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/C.php'],
                ]);
            }

            $threadId = $variables['threadId'] ?? '';
            $id = \is_string($threadId) ? $threadId : '';
            $mutations[] = $id;

            // The first mutation reports an error; the second succeeds.
            return $id === 'PRRT_first'
                ? (string) json_encode(['errors' => [['message' => 'forbidden']]])
                : self::resolveSuccess($id);
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(12);

        self::assertSame(2, $reviewThreadResolution->total);
        self::assertSame(1, $reviewThreadResolution->resolved);
        self::assertSame(1, $reviewThreadResolution->failed);
        self::assertSame(['PRRT_first', 'PRRT_second'], $mutations);
    }

    public function testNoFinishedConversationsMeansNoMutation(): void
    {
        $calls = 0;

        $graphql = static function (string $query, array $variables) use (&$calls): string {
            ++$calls;

            return self::threadsPayload([
                ['id' => 'PRRT_live', 'isResolved' => false, 'isOutdated' => false, 'path' => 'src/A.php'],
            ]);
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(7);

        self::assertSame(1, $reviewThreadResolution->total);
        self::assertSame(0, $reviewThreadResolution->resolved);
        self::assertSame(0, $reviewThreadResolution->failed);
        self::assertSame(1, $calls);
    }

    public function testMutationErrorIsCountedAsFailure(): void
    {
        $graphql = static function (string $query, array $variables): string {
            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_done', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                ]);
            }

            return (string) json_encode(['errors' => [['message' => 'forbidden']]]);
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(9);

        self::assertSame(1, $reviewThreadResolution->total);
        self::assertSame(0, $reviewThreadResolution->resolved);
        self::assertSame(1, $reviewThreadResolution->failed);
    }

    public function testMutationErrorWithASuccessfulDataSectionIsAFailure(): void
    {
        $graphql = static function (string $query, array $variables): string {
            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_done', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                ]);
            }

            // The errors array wins over the otherwise-successful data section.
            return (string) json_encode([
                'errors' => [['message' => 'boom']],
                'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_done', 'isResolved' => true]]],
            ]);
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(9);

        self::assertSame(0, $reviewThreadResolution->resolved);
        self::assertSame(1, $reviewThreadResolution->failed);
    }

    public function testMutationWithoutResolvedFlagIsAFailure(): void
    {
        $graphql = static function (string $query, array $variables): string {
            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_done', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                ]);
            }

            return (string) json_encode([
                'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_done', 'isResolved' => false]]],
            ]);
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(9);

        self::assertSame(1, $reviewThreadResolution->failed);
    }

    public function testMalformedMutationBodyIsAFailure(): void
    {
        $graphql = static function (string $query, array $variables): string {
            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_done', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                ]);
            }

            return 'not json';
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(9);

        self::assertSame(1, $reviewThreadResolution->failed);
    }

    public function testEmptyErrorsArrayIsNotAFailure(): void
    {
        $graphql = static function (string $query, array $variables): string {
            if (str_contains($query, 'reviewThreads')) {
                return self::threadsPayload([
                    ['id' => 'PRRT_done', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php'],
                ]);
            }

            return (string) json_encode([
                'errors' => [],
                'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_done', 'isResolved' => true]]],
            ]);
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(9);

        self::assertSame(1, $reviewThreadResolution->resolved);
        self::assertSame(0, $reviewThreadResolution->failed);
    }

    public function testMalformedThreadsPayloadPropagates(): void
    {
        $graphql = static fn (string $query, array $variables): string => 'not json';

        $this->expectException(ToolingException::class);

        new ReviewThreadResolver($graphql)->resolve(1);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private static function threadsPayload(array $nodes): string
    {
        return (string) json_encode([
            'data' => [
                'repository' => [
                    'pullRequest' => [
                        'reviewThreads' => [
                            'totalCount' => \count($nodes),
                            'nodes' => $nodes,
                        ],
                    ],
                ],
            ],
        ]);
    }

    private static function resolveSuccess(string $threadId): string
    {
        return (string) json_encode([
            'data' => [
                'resolveReviewThread' => [
                    'thread' => ['id' => $threadId, 'isResolved' => true],
                ],
            ],
        ]);
    }
}
