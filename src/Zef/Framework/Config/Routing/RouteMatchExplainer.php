<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Explains one match against a compiled radix tree by running the
 * matcher's own pipeline, staged and counted -- the diagnostic twin
 * of {@see RadixTree::match()}, never a second opinion.
 *
 * The pipeline is the matcher's, fold for fold: the path walk
 * ({@see RouteMatcher}), the host bucket ({@see RouteHostSelection}),
 * the version selection ({@see RouteVersionSelection}) and the
 * method-and-accept answer ({@see RouteMethodAnswer}) -- the same
 * units, the same order, the same inputs, so the explanation's
 * outcome is the match's outcome by construction (the suite pins
 * the equivalence). What the explainer adds is the counting between
 * the folds -- how many candidates each stage kept -- and, when a
 * stage emptied the set, the stage's own name and the payload its
 * refusal carries: the neighborhood for a path-stage miss
 * ({@see RouteMatchNeighborhood}), the allowed methods for a
 * method-stage refusal, the producible media types for an
 * accept-stage refusal.
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
        RadixNode $root,
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): RouteMatchExplanation {
        $captured = [];
        $walked = (new RouteMatcher())->walk($root, $path, 0, $captured);

        if ($walked === []) {
            $neighborhood = RouteMatchNeighborhood::around($root, $path);

            return RouteMatchExplanation::pathMiss(
                $neighborhood['prefix'],
                $neighborhood['patterns'],
            );
        }

        $requestHost = RouteHostRequest::canonicalise($host);
        $hostSelected = RouteHostSelection::select($walked, $requestHost);

        if ($hostSelected === []) {
            return RouteMatchExplanation::hostMiss(\count($walked), 0);
        }

        $versionSelected = RouteVersionSelection::select($hostSelected, $version);

        if ($versionSelected === []) {
            return RouteMatchExplanation::versionMiss(\count($walked), \count($hostSelected));
        }

        $matchResult = RouteMethodAnswer::resolve($method, $versionSelected, $captured, $requestHost, $accept);

        if ($matchResult->isFound()) {
            return RouteMatchExplanation::found(
                \count($walked),
                \count($hostSelected),
                \count($versionSelected),
            );
        }

        if ($matchResult->isMethodNotAllowed()) {
            return RouteMatchExplanation::methodRefusal(
                $matchResult->allowedMethods(),
                \count($walked),
                \count($hostSelected),
                \count($versionSelected),
            );
        }

        return RouteMatchExplanation::acceptRefusal(
            $matchResult->acceptableMediaTypes(),
            \count($walked),
            \count($hostSelected),
            \count($versionSelected),
        );
    }
}
