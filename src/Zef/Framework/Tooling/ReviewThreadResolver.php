<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Closure;

/**
 * Resolves the *finished* review conversations on a pull request so the branch
 * ruleset's `required_review_thread_resolution` rule stops blocking the merge.
 *
 * WHY THIS EXISTS
 * ---------------
 * The `main` ruleset requires every review conversation to be resolved before a
 * pull request may merge. A conversation that is anchored to a diff hunk which
 * no longer exists on the current head is reported by GitHub as `isOutdated`:
 * the code moved on, so the conversation is finished and can never be acted on
 * again -- yet it still counts as unresolved and keeps the pull request
 * `blocked` forever. This resolver closes exactly those threads and nothing
 * else.
 *
 * SAFETY BOUNDARY
 * ---------------
 * Only threads that are BOTH unresolved AND outdated are resolved. A thread that
 * is unresolved but still anchored to live code is a real reviewer objection and
 * is left untouched -- dismissing it automatically would be a correctness bug,
 * not a convenience. The decision lives in {@see ReviewThread::isFinishedConversation()}.
 *
 * The class performs no I/O itself: the GraphQL transport is injected as a
 * closure, so the whole decision path is exercised deterministically in tests.
 */
final readonly class ReviewThreadResolver
{
    private const string THREADS_QUERY = <<<'GRAPHQL'
        query($number: Int!) {
          repository(owner: "zeflous", name: "zeflous") {
            pullRequest(number: $number) {
              reviewThreads(first: 100) {
                totalCount
                nodes { id isResolved isOutdated path }
              }
            }
          }
        }
        GRAPHQL;

    private const string RESOLVE_MUTATION = <<<'GRAPHQL'
        mutation($threadId: ID!) {
          resolveReviewThread(input: {threadId: $threadId}) {
            thread { id isResolved }
          }
        }
        GRAPHQL;

    /**
     * @param Closure(string, array<string, mixed>): string $graphql graphQL transport
     */
    public function __construct(private Closure $graphql)
    {
    }

    public function resolve(int $pullRequestNumber): ReviewThreadResolution
    {
        $payload = ($this->graphql)(self::THREADS_QUERY, ['number' => $pullRequestNumber]);

        $reviewThreadReport = new ReviewThreadPayload($payload)->report();

        $resolved = 0;
        $failed = 0;

        foreach ($reviewThreadReport->resolvableIds() as $threadId) {
            $response = ($this->graphql)(self::RESOLVE_MUTATION, ['threadId' => $threadId]);

            if (ReviewThreadMutation::succeeded($response)) {
                ++$resolved;
            } else {
                ++$failed;
            }
        }

        return new ReviewThreadResolution($reviewThreadReport->count(), $resolved, $failed);
    }
}
