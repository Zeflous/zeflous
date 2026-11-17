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
 * The entry map's key is the method, a NUL separator and the owning
 * host's own spelling (the host-agnostic claim's empty spelling) --
 * the sort over that key IS the deterministic order (the method
 * ascending, then the owning host ascending with the host-agnostic
 * claim first among its own method's peers, because a shorter key
 * sorts before its own extensions) and the duplicate fold IS the same
 * map (two routes behind one claim spelling answer one entry). No
 * comparator, no seen-set: one vocabulary owns both disciplines.
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

        $selectedRouteIds = [];

        foreach ($selected as $route) {
            $selectedRouteIds[spl_object_id($route)] = true;
        }

        $shadowedMethods = [];

        foreach ($walked as $route) {
            if (isset($selectedRouteIds[spl_object_id($route)])) {
                continue;
            }

            foreach (RouteHostShadowedRoute::entries($route, $requestHost) as $entry) {
                $shadowedMethods[$entry['method'] . "\x00" . ($entry['host'] ?? '')] = $entry;
            }
        }

        ksort($shadowedMethods, \SORT_STRING);

        return array_values($shadowedMethods);
    }
}
