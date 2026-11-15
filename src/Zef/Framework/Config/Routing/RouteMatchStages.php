<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The match pipeline's staged product (#794's gate round): every fold
 * the engine's own order runs -- the path walk, the host bucket, the
 * version selection, and the miss-or-method answer -- answering the
 * intermediate sets the counting twin ({@see RouteMatchExplainer})
 * needs, in the one spelling both callers share so the explanation's
 * outcome is the match's outcome *by construction*, not by a mirrored
 * copy that can drift.
 *
 * {@see RouteMatchPipeline::match()} is the hot path's thin reader of
 * the result member; the explainer destructures all four. The fold
 * owns nothing but the sequence -- the tree owns the seams, the
 * folds own the judgements.
 *
 * @internal
 */
final class RouteMatchStages
{
    /**
     * Runs the engine's own order and answers its four stages: the
     * walked candidates, the host bucket's selection, the version
     * selection, and the match result the sequence settled on.
     *
     * @return array{walked: list<CompiledRoute>, host: list<CompiledRoute>, version: list<CompiledRoute>, result: MatchResult}
     */
    public static function run(
        RadixNode $radixNode,
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): array {
        $captured = [];
        $walked = new RouteMatcher()->walk($radixNode, $path, 0, $captured);

        $requestHost = RouteHostRequest::canonicalise($host);
        $hostSelected = RouteHostSelection::select($walked, $requestHost);
        $versionSelected = RouteVersionSelection::select($hostSelected, $version);

        if ($versionSelected !== []) {
            $result = RouteMethodAnswer::resolve($method, $versionSelected, $captured, $requestHost, $accept);
        } elseif ($hostSelected === []) {
            $result = MatchResult::notFound();
        } else {
            $result = RouteVersionMiss::answer($method, $hostSelected);
        }

        return [
            'walked' => $walked,
            'host' => $hostSelected,
            'version' => $versionSelected,
            'result' => $result,
        ];
    }
}
