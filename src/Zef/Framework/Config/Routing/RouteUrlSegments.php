<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The URL segments of one route, rendered from its parameter values.
 *
 * Static segments pass through verbatim; every parameter segment is
 * rendered by {@see RouteParameterRenderer} from the given map, and the
 * map as a whole is judged against the route's own parameter names by
 * {@see RouteParameterSet} -- a parameter the route never asked for is
 * a defect, not something to silently drop.
 *
 * @internal
 */
final class RouteUrlSegments
{
    /**
     * Renders the route's URL segments.
     *
     * @param array<string, mixed> $params one value per route parameter
     *
     * @return list<string> the rendered segments, in path order
     */
    public static function render(CompiledRoute $compiledRoute, array $params): array
    {
        $rendered = [];

        foreach ($compiledRoute->path()->segments() as $pathSegment) {
            if (!$pathSegment->isParameter()) {
                $rendered[] = $pathSegment->value();

                continue;
            }

            $rendered[] = RouteParameterRenderer::render($compiledRoute, $pathSegment->value(), $params);
        }

        RouteParameterSet::assertNoExcess($compiledRoute, $params);

        return $rendered;
    }
}
