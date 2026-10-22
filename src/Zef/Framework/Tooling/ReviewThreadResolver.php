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
 * closure, so the whole decision path is exercised deterministically in
 * tests.
 *
 * PAGINATION
 * ----------
 * The `reviewThreads` connection caps at one hundred nodes a page, and
 * long-lived bot pull requests accumulate more threads than that: the
 * walk follows the page's own `endCursor` until `hasNextPage` answers
 * false ({@see ReviewThreadPageInfo}), so a thread beyond the first
 * page is never silently left unresolved while the sweep reports
 * success.
 */
final readonly class ReviewThreadResolver
{
    private const string THREADS_QUERY = <<<'GRAPHQL'
        query($number: Int!, $cursor: String) {
          repository(owner: "zeflous", name: "zeflous") {
            pullRequest(number: $number) {
              reviewThreads(first: 100, after: $cursor) {
                totalCount
                pageInfo { hasNextPage endCursor }
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
        $report = $this->report($pullRequestNumber);

        $resolved = 0;
        $failed = 0;

        foreach ($report->resolvableIds() as $threadId) {
            $response = ($this->graphql)(self::RESOLVE_MUTATION, ['threadId' => $threadId]);

            if (ReviewThreadMutation::succeeded($response)) {
                ++$resolved;
            } else {
                ++$failed;
            }
        }

        // The unusable nodes ride the failure total: a thread the sweep
        // cannot account for still counts as unresolved for the required
        // check, so the sweep's answer names it instead of reporting a
        // clean zero over an unaccounted thread.
        return new ReviewThreadResolution($report->count(), $resolved, $failed, $report->unusable);
    }

    /**
     * The pull request's review threads, every page of them: the walk
     * starts at the first page (a null cursor) and follows the page's
     * own `endCursor` until `hasNextPage` answers false -- each page's
     * parsed report folding into one, threads and unusable nodes alike
     * (the sweep's answer accounts for every node the pages carried).
     */
    private function report(int $pullRequestNumber): ReviewThreadReport
    {
        $threads = [];
        $unusable = 0;
        $cursor = null;

        do {
            $payload = ($this->graphql)(
                self::THREADS_QUERY,
                ['number' => $pullRequestNumber, 'cursor' => $cursor],
            );

            $page = new ReviewThreadPageInfo($payload)->page();
            $parsed = new ReviewThreadPayload($payload)->report();

            $threads = [...$threads, ...$parsed->threads];
            $unusable += $parsed->unusable;

            $cursor = $page['endCursor'];
        } while ($page['hasNextPage']);

        return new ReviewThreadReport($threads, $unusable);
    }
}
