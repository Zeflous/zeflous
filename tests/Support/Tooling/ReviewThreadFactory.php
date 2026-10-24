<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Builds a {@see ReviewThread} from one raw GitHub GraphQL `reviewThreads` node.
 *
 * The parsing lives here rather than on the value object so each class stays
 * small and single-purpose: the value object carries the facts, this factory
 * knows how to read them out of the `mixed` shape json_decode() produces.
 *
 * A non-array node is cast to an empty-ish array, so every unusable node --
 * a scalar, null, a list without the expected keys -- simply yields no
 * thread instead of needing its own guard branch.
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
        $fields = (array) $node;

        $id = $fields['id'] ?? null;

        if (!\is_string($id) || $id === '') {
            return null;
        }

        return new ReviewThread(
            $id,
            ($fields['isResolved'] ?? false) === true,
            ($fields['isOutdated'] ?? false) === true,
            self::path($fields['path'] ?? null),
        );
    }

    /**
     * The node's path member judged at the fold's own seam: the mixed
     * member crosses straight into the parameter, and a path that is
     * not a string is the empty spelling, never a guess.
     *
     * @param mixed $path the node's path member, as the payload carried it
     */
    private static function path(mixed $path): string
    {
        return \is_string($path) ? $path : '';
    }
}
