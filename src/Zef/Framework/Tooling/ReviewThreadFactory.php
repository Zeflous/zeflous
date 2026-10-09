<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Builds a {@see ReviewThread} from one raw GitHub GraphQL `reviewThreads` node.
 *
 * The parsing lives here rather than on the value object so each class stays
 * small and single-purpose: the value object carries the facts, this factory
 * knows how to read them out of the `mixed` shape json_decode() produces.
 */
final readonly class ReviewThreadFactory
{
    /**
     * Returns the thread, or null when the node is unusable.
     *
     * A thread without a usable id cannot be resolved through the API, so it is
     * skipped instead of producing an empty-id mutation.
     */
    public static function fromNode(mixed $node): ?ReviewThread
    {
        if (!\is_array($node)) {
            return null;
        }

        $id = $node['id'] ?? null;

        if (!\is_string($id) || $id === '') {
            return null;
        }

        $path = $node['path'] ?? null;

        return new ReviewThread(
            $id,
            ($node['isResolved'] ?? false) === true,
            ($node['isOutdated'] ?? false) === true,
            \is_string($path) ? $path : '',
        );
    }
}
