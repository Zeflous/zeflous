<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The route table's neighborhood of one request path: the deepest
 * node the path's static text still descends into, and the patterns
 * that live under it -- the "what you probably meant" a path-stage
 * miss explains itself with.
 *
 * The unit is the orchestrator over the two single-question folds
 * the walk's own vocabulary owns: {@see RouteMatchDescent} answers
 * how far the request's text tracked the table (a partially
 * continued run counts its shared head, the tree builder's own
 * measure), and {@see RouteSubtreePatterns} answers what lives past
 * that point (the capped, breadth-first candidate patterns, host
 * claims spelled, parameter edges included as patterns -- never as
 * descent steps, because a descent that guessed what a
 * `{parameter}` would have captured would be the second opinion
 * this unit refuses to give).
 *
 * @internal
 */
final class RouteMatchNeighborhood
{
    /**
     * Reads the neighborhood of a request path from the tree's root.
     *
     * @return array{prefix: string, patterns: list<string>} the deepest
     *                                                       static prefix the path tracked, verbatim from the path, and
     *                                                       the capped, breadth-first patterns under it
     */
    public static function around(RadixNode $radixNode, string $path): array
    {
        [$node, $offset] = RouteMatchDescent::deepest($radixNode, $path);

        return [
            'prefix' => mb_substr($path, 0, $offset),
            'patterns' => RouteSubtreePatterns::collect($node),
        ];
    }
}
