<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The shadow contribution of ONE route the host specificity dropped
 * (#795): the entries it would have answered had the winning claim
 * not owned the path -- each method it declares, each carrying the
 * host claim that owns it.
 *
 * The judgment is the selection's own predicates replayed on the
 * dropped route: a host-agnostic claim answers every host (it is
 * shadowed wherever a claim outranks it), and a host-claiming route
 * answers only the request hosts its claim matches ({@see
 * CompiledRoute::hostParameters()} -- the same fold
 * {@see RouteHostSelection} judged the bucket by, consulted through
 * the same canonical request host spelling). A route whose claim does
 * NOT answer the request host contributes nothing: it is merely
 * elsewhere, and naming it would pretend a method one host-hop away
 * belongs to this request's answer.
 *
 * Extracted from {@see RouteHostShadowedMethods} because the
 * archeology difficulty ceiling scores a class that carries both the
 * selection diff and this judgment above 20 -- the same lesson
 * `ForwardedProtocolClaim` taught the forwarded-scheme round (#797):
 * the vocabulary a fold consults belongs to its own unit.
 *
 * @internal
 */
final class RouteHostShadowedRoute
{
    /**
     * One dropped route's entries: the methods it answers, each with
     * the host claim that owns it.
     *
     * @param CompiledRoute $compiledRoute a route the host stage did not select
     * @param null|string   $requestHost   the request host the selection judged
     *
     * @return list<array{method: string, host: null|string}> the route's
     *                                                        shadow entries, empty
     *                                                        when the route is
     *                                                        merely elsewhere
     */
    public static function entries(CompiledRoute $compiledRoute, ?string $requestHost): array
    {
        $host = $compiledRoute->host();

        if ($host !== null && $compiledRoute->hostParameters($requestHost) === null) {
            return [];
        }

        $entries = [];

        foreach ($compiledRoute->methods() as $method) {
            $entries[] = ['method' => $method, 'host' => $host];
        }

        return $entries;
    }
}
