<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Explains one match against a compiled radix tree by running the
 * matcher's own pipeline, staged and counted -- the diagnostic twin
 * of {@see RadixTree::match()}, never a second opinion.
 *
 * The pipeline is the matcher's, literally: the stages the engine's
 * own order runs ({@see RouteMatchStages} -- the path walk, the host
 * bucket, the version selection, and the miss-or-method answer) are
 * the ones this twin destructures and counts, so the explanation's
 * outcome is the match's outcome by construction (the suite pins the
 * equivalence), including the version stage's miss fold (#794).
 * What the explainer adds is the counting between the stages -- how
 * many candidates each kept -- and, when a stage emptied the set, the
 * stage's own name and the payload its refusal carries: the
 * neighborhood for a path-stage miss ({@see RouteMatchNeighborhood}),
 * the allowed methods for a method-stage refusal or a version-stage
 * miss whose method judgement answered the 405, the producible media
 * types for an accept-stage refusal.
 *
 * A found match explains itself as found, carrying only the stage
 * counts -- the diagnostic twin of a successful match has no failure
 * to attribute, and inventing one would be the second opinion this
 * class refuses to be.
 *
 * @internal
 */
final class RouteMatchExplainer
{
    /**
     * Explains the match of a request method, path, host, Accept and
     * API-version against the tree's root node.
     */
    public static function explain(
        RadixNode $radixNode,
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): RouteMatchExplanation {
        [
            'result' => $matchResult,
            'host' => $hostSelected,
            'version' => $versionSelected,
            'walked' => $walked,
        ] = RouteMatchStages::run($radixNode, $method, $path, $host, $accept, $version);

        return match (true) {
            $walked === [] => self::pathMissExplanation($radixNode, $path),
            $hostSelected === [] => RouteMatchExplanation::hostMiss(\count($walked), 0),
            $versionSelected === [] && $matchResult->isMethodNotAllowed() => RouteMatchExplanation::versionMissMethodRefusal(
                $matchResult->allowedMethods(),
                \count($walked),
                \count($hostSelected),
            ),
            $versionSelected === [] => RouteMatchExplanation::versionMiss(
                \count($walked),
                \count($hostSelected),
            ),
            $matchResult->isMethodNotAllowed() => RouteMatchExplanation::methodRefusal(
                $matchResult->allowedMethods(),
                \count($walked),
                \count($hostSelected),
                \count($versionSelected),
            ),
            $matchResult->isFound() => RouteMatchExplanation::found(
                \count($walked),
                \count($hostSelected),
                \count($versionSelected),
            ),
            default => RouteMatchExplanation::acceptRefusal(
                $matchResult->acceptableMediaTypes(),
                \count($walked),
                \count($hostSelected),
                \count($versionSelected),
            ),
        };
    }

    /**
     * The path-stage miss and its neighborhood, folded out of the
     * pipeline's answer so the match expression stays one spelling
     * per outcome.
     */
    private static function pathMissExplanation(RadixNode $radixNode, string $path): RouteMatchExplanation
    {
        $neighborhood = RouteMatchNeighborhood::around($radixNode, $path);

        return RouteMatchExplanation::pathMiss(
            $neighborhood['prefix'],
            $neighborhood['patterns'],
        );
    }
}
