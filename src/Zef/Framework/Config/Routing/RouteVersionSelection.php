<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Version selection among the routes a host bucket holds: versioned
 * variants answer an explicit request version, unversioned routes act as
 * the fallback only when no explicit version was supplied, and an unknown
 * explicit version fails closed (an empty selection, i.e. not found).
 *
 * One policy for every spelling of the path (#794): an explicit version
 * is a binding intent whether the path carries versioned routes,
 * unversioned ones, or both -- it is never quietly ignored because the
 * path happens to carry only unversioned routes, and the unversioned
 * fallback is never served beside a named version that does not exist.
 * The empty selection's own answer belongs to the pipeline's miss fold
 * ({@see RouteVersionMiss}): the method judgement over the candidates
 * still runs, so a version miss with a wrong method reports the 405 the
 * method deserves instead of masking it behind a bare 404.
 *
 * @internal
 */
final class RouteVersionSelection
{
    /**
     * Selects the routes the request version answers.
     *
     * @param list<CompiledRoute> $routes the host-selected candidates
     *
     * @return list<CompiledRoute> the versioned-or-fallback selection (possibly empty)
     */
    public static function select(array $routes, ?string $version): array
    {
        if ($version === null || mb_trim($version) === '') {
            return array_values(array_filter(
                $routes,
                static fn (CompiledRoute $compiledRoute): bool => $compiledRoute->version() === null,
            ));
        }

        $requestVersion = RouteVersion::request($version);

        if ($requestVersion === null) {
            return [];
        }

        return array_values(array_filter(
            $routes,
            static fn (CompiledRoute $compiledRoute): bool => $compiledRoute->version() === $requestVersion,
        ));
    }
}
