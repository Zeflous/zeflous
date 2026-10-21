<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Version selection among the routes a host bucket holds: versioned
 * variants answer an explicit request version, unversioned routes act as
 * the fallback only when no explicit version was supplied, and an unknown
 * explicit version fails closed (an empty selection, i.e. not found).
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
        $versionedRoutes = array_values(array_filter(
            $routes,
            static fn (CompiledRoute $compiledRoute): bool => $compiledRoute->version() !== null,
        ));

        if ($versionedRoutes === [] || $version === null || mb_trim($version) === '') {
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
            $versionedRoutes,
            static fn (CompiledRoute $compiledRoute): bool => $compiledRoute->version() === $requestVersion,
        ));
    }
}
