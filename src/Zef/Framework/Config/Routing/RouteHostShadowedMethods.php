<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The host stage's shadow answer (#795): the methods the settled path
 * answers through the routes the specificity selection DROPPED --
 * the diagnostic union across the host buckets, each method carrying
 * the host claim that owns it, so an operator reading a 405 whose
 * `Allow` names only the winning claim's methods can still see what
 * the same path answers behind the other claims.
 *
 * The dispatch answer stays what it always was ({@see
 * RouteAllowedMethods} over the SELECTED set): the winning claim owns
 * the whole path at its host for every method, so the response's own
 * `Allow` header naming the claim's methods is the honest spelling for
 * the resource as dispatched -- a method the selection dropped is not
 * dispatchable at that host, and listing it in the header would invite
 * a retry the table would refuse again. What the 405 lacked is the
 * other half: the routes a lower claim declared for the same path
 * were invisible everywhere ({@see RouteHostSelection}'s either/or
 * contract), so an API consumer could not tell a host-exact claim
 * from a table that genuinely answers one method. This fold answers
 * them, and only them, to the explanation lane ({@see
 * RouteMatchExplainer}), whose summary ({@see RouteMatchSummary}),
 * debug header ({@see \Zef\Framework\Middleware\RouteDebugMiddleware})
 * and `route:test` verb carry the diagnostic the audit asked for.
 *
 * The entry map is the nested tuple (method => owning host => entry),
 * the KDD-HW7 lesson replayed on this fold's own key: no separator
 * grammar can alias two different (method, host) tuples onto one
 * string, and no concat mutant has a target. The two `ksort`s spell
 * the deterministic order -- the method ascending, then the owning
 * host ascending with the host-agnostic claim (the empty spelling)
 * first among its own method's peers -- and the nested key IS the
 * duplicate fold (two routes behind one claim spelling answer one
 * entry). The selected identity map carries the route itself as its
 * value, never a bare `true`: a boolean nobody reads is a mutant
 * target, the identity the map holds is not.
 *
 * @internal
 */
final class RouteHostShadowedMethods
{
    /**
     * The shadowed methods of one host selection, each with the host
     * claim that owns it (null: the host-agnostic claim).
     *
     * @param list<CompiledRoute> $walked      the candidates the path walk settled
     * @param list<CompiledRoute> $selected    the routes the host stage selected
     * @param null|string         $requestHost the request host the selection judged
     *
     * @return list<array{method: string, host: null|string}> the shadowed
     *                                                        methods, sorted by
     *                                                        method then host, the
     *                                                        host-agnostic entry
     *                                                        first among its own
     *                                                        method's peers
     */
    public static function of(array $walked, array $selected, ?string $requestHost): array
    {
        if ($selected === []) {
            return [];
        }

        $selectedRoutes = [];

        foreach ($selected as $route) {
            $selectedRoutes[spl_object_id($route)] = $route;
        }

        $shadowedMethods = [];

        foreach ($walked as $route) {
            if (isset($selectedRoutes[spl_object_id($route)])) {
                continue;
            }

            foreach (RouteHostShadowedRoute::entries($route, $requestHost) as $entry) {
                $shadowedMethods[$entry['method']][$entry['host'] ?? ''] = $entry;
            }
        }

        ksort($shadowedMethods, \SORT_STRING);

        $entries = [];

        foreach ($shadowedMethods as $shadowedMethod) {
            ksort($shadowedMethod, \SORT_STRING);

            foreach ($shadowedMethod as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
