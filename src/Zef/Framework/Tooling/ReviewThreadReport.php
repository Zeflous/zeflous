<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The review conversations of one pull request, as parsed from the GitHub
 * GraphQL `reviewThreads` payload by {@see ReviewThreadPayload}.
 *
 * Pure data: this report exposes the facts the resolver needs (which
 * conversations are finished, how many there are) without touching JSON or
 * the filesystem, so its behaviour is deterministic and fully unit-testable.
 */
final readonly class ReviewThreadReport
{
    /**
     * @param list<ReviewThread> $threads  the parsed threads, in payload order
     * @param int                $unusable the nodes the factory could not read
     *                                      (null, deleted, permission-restricted) -- threads
     *                                      the sweep cannot account for but the
     *                                      required check still counts as unresolved
     */
    public function __construct(public array $threads, public int $unusable = 0)
    {
    }

    /**
     * The ids of every thread that is a finished conversation, in payload order.
     *
     * @return list<string>
     */
    public function resolvableIds(): array
    {
        $ids = [];

        foreach ($this->threads as $thread) {
            if (!$thread->isFinishedConversation()) {
                continue;
            }

            $ids[] = $thread->id;
        }

        return $ids;
    }

    public function count(): int
    {
        return \count($this->threads);
    }
}
