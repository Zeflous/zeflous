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
    /**
     * @param Closure(string, array<string, mixed>): string $graphql graphQL transport
     */
    public function __construct(private Closure $graphql)
    {
    }

    public function resolve(int $pullRequestNumber): ReviewThreadResolution
    {
        $payload = ($this->graphql)(
            $this->threadsQuery(),
            ['number' => $pullRequestNumber],
        );

        $reviewThreadReport = ReviewThreadReport::fromGraphQlPayload($payload);
        $resolvable = $reviewThreadReport->resolvableIds();

        $resolved = 0;
        $failed = 0;

        foreach ($resolvable as $threadId) {
            $response = ($this->graphql)(
                $this->resolveMutation(),
                ['threadId' => $threadId],
            );

            if ($this->mutationSucceeded($response)) {
                ++$resolved;

                continue;
            }

            ++$failed;
        }

        return new ReviewThreadResolution($reviewThreadReport->count(), $resolved, $failed);
    }

    /**
     * A mutation response is a success only when it carries no `errors` array
     * and reports the thread as resolved. Anything else (transport error, a
     * permission error, a malformed body) is a failure the caller must surface
     * rather than swallow.
     */
    private function mutationSucceeded(string $response): bool
    {
        $decoded = json_decode($response, true);

        if (!\is_array($decoded)) {
            return false;
        }

        if (isset($decoded['errors']) && \is_array($decoded['errors']) && $decoded['errors'] !== []) {
            return false;
        }

        // Narrow the nested `mixed` payload level by level before indexing it.
        $data = $decoded['data'] ?? null;
        $resolve = \is_array($data) ? $data['resolveReviewThread'] ?? null : null;
        $thread = \is_array($resolve) ? $resolve['thread'] ?? null : null;

        return \is_array($thread) && ($thread['isResolved'] ?? false) === true;
    }

    private function threadsQuery(): string
    {
        return <<<'GRAPHQL'
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
    }

    private function resolveMutation(): string
    {
        return <<<'GRAPHQL'
            mutation($threadId: ID!) {
              resolveReviewThread(input: {threadId: $threadId}) {
                thread { id isResolved }
              }
            }
            GRAPHQL;
    }
}
