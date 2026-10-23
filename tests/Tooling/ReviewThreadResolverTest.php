<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\ReviewThreadPageInfo;
use Zef\Test\Support\Tooling\ReviewThreadResolver;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(ReviewThreadResolver::class)]
#[CoversClass(ReviewThreadPageInfo::class)]
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
     * The connection caps at one hundred nodes a page: a pull request
     * with more threads answers its later pages only behind the cursor,
     * and the walk must follow -- a thread beyond the first page is
     * exactly the silently-unresolved thread that keeps the PR blocked
     * while the sweep reports success.
     */
    public function testResolvesThreadsBeyondTheFirstPage(): void
    {
        $calls = [];

        $graphql = static function (string $query, array $variables) use (&$calls): string {
            $calls[] = $variables;

            if (str_contains($query, 'reviewThreads')) {
                if (($variables['cursor'] ?? null) === null) {
                    return self::pagedThreadsPayload(
                        [['id' => 'PRRT_page1', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/A.php']],
                        true,
                        'CURSOR_1',
                        2,
                    );
                }

                return self::pagedThreadsPayload(
                    [['id' => 'PRRT_page2', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php']],
                    false,
                    null,
                    2,
                );
            }

            $threadId = $variables['threadId'] ?? '';

            return self::resolveSuccess(\is_string($threadId) ? $threadId : '');
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(131);

        self::assertSame(2, $reviewThreadResolution->total);
        self::assertSame(2, $reviewThreadResolution->resolved);
        self::assertSame(0, $reviewThreadResolution->failed);
        self::assertCount(4, $calls, 'two page queries and two mutations');
        self::assertNull($calls[0]['cursor']);
        self::assertSame('CURSOR_1', $calls[1]['cursor']);
        self::assertSame('PRRT_page1', $calls[2]['threadId']);
        self::assertSame('PRRT_page2', $calls[3]['threadId']);
    }

    /**
     * The cross-check's refusal arm: a `pageInfo` that ends the walk early
     * (hasNextPage false) while the connection's own total says more nodes
     * exist is a truncated walk, and a truncated walk must refuse rather
     * than resolve against a ledger it cannot see whole.
     */
    public function testATruncatedWalkRefusesAgainstTheConnectionTotal(): void
    {
        $calls = 0;

        $graphql = static function (string $query, array $variables) use (&$calls): string {
            if (str_contains($query, 'reviewThreads')) {
                ++$calls;

                return self::pagedThreadsPayload(
                    [['id' => 'PRRT_page1', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/A.php']],
                    false,
                    null,
                    2,
                );
            }

            return self::resolveSuccess('');
        };

        try {
            new ReviewThreadResolver($graphql)->resolve(131);
            self::fail('A truncated walk must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread pagination is inconsistent: 1 thread(s) walked against a total of 2.',
                $toolingException->getMessage(),
            );
        }

        self::assertSame(1, $calls, 'no mutation may fire for a walk that refused');
    }

    /**
     * A page that claims another follows while carrying no cursor to follow
     * is refused outright: the walk would re-send the first page's query
     * forever, duplicating its threads until the job's own timeout.
     */
    public function testACursorlessContinuationIsRefusedInsteadOfReWalkingForever(): void
    {
        $calls = 0;

        $graphql = static function (string $query, array $variables) use (&$calls): string {
            if (str_contains($query, 'reviewThreads')) {
                ++$calls;

                return self::pagedThreadsPayload(
                    [['id' => 'PRRT_page1', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/A.php']],
                    true,
                    null,
                    1,
                );
            }

            return self::resolveSuccess('');
        };

        try {
            new ReviewThreadResolver($graphql)->resolve(131);
            self::fail('A cursorless continuation must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'Review-thread pagination is inconsistent: a page that follows carries no cursor to follow.',
                $toolingException->getMessage(),
            );
        }

        self::assertSame(1, $calls, 'the refusal fired on the first page, not after a re-walk');
    }

    /**
     * Unusable nodes ride the failure total across every page of the
     * walk: a thread the sweep cannot account for still counts as
     * unresolved for the required check, so one unusable node on each
     * of two pages answers two -- never the last page's count alone,
     * never a negative or padded total.
     */
    public function testUnusableNodesOnEveryPageRideTheFailureTotal(): void
    {
        $graphql = static function (string $query, array $variables): string {
            if (str_contains($query, 'reviewThreads')) {
                if (($variables['cursor'] ?? null) === null) {
                    return self::pagedThreadsPayload(
                        [['isResolved' => false, 'isOutdated' => true, 'path' => 'src/A.php']],
                        true,
                        'CURSOR_1',
                        2,
                    );
                }

                return self::pagedThreadsPayload(
                    [['isResolved' => false, 'isOutdated' => true, 'path' => 'src/B.php']],
                    false,
                    null,
                    2,
                );
            }

            return self::resolveSuccess('');
        };

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(131);

        self::assertSame(0, $reviewThreadResolution->total, 'an id-less node is not a thread the sweep can act on');
        self::assertSame(0, $reviewThreadResolution->resolved);
        self::assertSame(0, $reviewThreadResolution->failed);
        self::assertSame(
            2,
            $reviewThreadResolution->unusable,
            'one id-less node per page rides the failure total as two unaccounted threads',
        );
    }

    public function testASinglePageWithoutUnusableNodesAnswersZero(): void
    {
        $graphql = static fn (string $query, array $variables): string => self::threadsPayload([
            ['id' => 'PRRT_live', 'isResolved' => false, 'isOutdated' => false, 'path' => 'src/A.php'],
        ]);

        $reviewThreadResolution = new ReviewThreadResolver($graphql)->resolve(131);

        self::assertSame(0, $reviewThreadResolution->unusable);
        self::assertSame(0, $reviewThreadResolution->failed);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private static function threadsPayload(array $nodes): string
    {
        return self::pagedThreadsPayload($nodes, false, null);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private static function pagedThreadsPayload(
        array $nodes,
        bool $hasNextPage,
        ?string $endCursor,
        ?int $totalCount = null,
    ): string {
        return (string) json_encode([
            'data' => [
                'repository' => [
                    'pullRequest' => [
                        'reviewThreads' => [
                            // The connection-wide total, constant on every
                            // page -- the semantics GitHub answers and the
                            // resolver's cross-check walks against.
                            'totalCount' => $totalCount ?? \count($nodes),
                            'pageInfo' => [
                                'hasNextPage' => $hasNextPage,
                                'endCursor' => $endCursor,
                            ],
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
