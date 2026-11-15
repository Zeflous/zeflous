<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The version miss's method judgement (#794): what a request whose
 * version the selection could not answer is told -- the fold the
 * pipeline runs when {@see RouteVersionSelection} emptied the set
 * while the path itself matched.
 *
 * The miss is judged by the *method*, over the whole candidate set
 * the host bucket handed down: a method no candidate answers is the
 * client's first fixable defect, so the honest answer is the 405 with
 * every method the path's routes do answer ({@see
 * RouteAllowedMethods}) -- the client is never left guessing whether
 * the method or the version was wrong, and the same request shape
 * answers the same whether the path carries versioned routes,
 * unversioned ones, or both. A method some candidate *does* answer
 * leaves the version as the failure -- the fail-closed 404 that never
 * serves a route at a version the request did not name, and never
 * advertises which versions would have answered.
 *
 * The miss never founds: the routes that would answer the method
 * belong to versions the selection already refused.
 *
 * @internal
 */
final class RouteVersionMiss
{
    /**
     * The answer for a version selection that emptied while the
     * path's candidates stand: the method-not-allowed verdict when no
     * candidate answers the method, the not-found verdict when one
     * does (the version is the failure then, and it fails closed).
     *
     * @param list<CompiledRoute> $candidates the host-selected routes the
     *                                        version selection could not
     *                                        narrow
     */
    public static function answer(string $method, array $candidates): MatchResult
    {
        foreach ($candidates as $route) {
            if ($route->answersMethod($method)) {
                return MatchResult::notFound();
            }
        }

        return MatchResult::methodNotAllowed(RouteAllowedMethods::of($candidates));
    }
}
