<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * One review conversation on a pull request, as reported by the GitHub GraphQL
 * `reviewThreads` connection.
 *
 * A thread is the unit the branch ruleset's `required_review_thread_resolution`
 * rule counts: while ANY thread is unresolved, GitHub refuses to merge the pull
 * request even when every status check is green. This value object carries the
 * three facts the resolver needs to decide whether a thread is a *finished*
 * conversation (outdated) or a *live* one that must be left for a human.
 */
final readonly class ReviewThread
{
    public function __construct(
        public string $id,
        public bool $isResolved,
        public bool $isOutdated,
        public string $path,
    ) {
    }

    /**
     * A thread is a candidate for automatic resolution only when it is BOTH
     * unresolved AND outdated.
     *
     * `isOutdated` is GitHub's own signal that the diff hunk the conversation
     * was anchored to no longer exists on the current head -- the code moved on,
     * so the conversation is finished and can never be acted on again. A thread
     * that is unresolved but NOT outdated is a live reviewer concern and is
     * deliberately left untouched: silently resolving it would dismiss a real
     * objection, which is exactly the failure mode this lane must avoid.
     */
    public function isFinishedConversation(): bool
    {
        return !$this->isResolved && $this->isOutdated;
    }
}
