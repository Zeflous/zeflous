<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The specificity-ordered host-claim bucket of a selection round: routes
 * whose declared host matched the request host, keeping only the offers of
 * the highest specificity seen so far. An exact host arrives as the most
 * specific claim possible (every label literal), so it wins the bucket by
 * the same ordering a wildcard or named pattern is judged by.
 *
 * @internal
 */
final class RouteHostPatternBucket
{
    /** @var list<CompiledRoute> */
    private array $routes = [];

    private int $specificity = -1;

    /**
     * Offers a matched pattern route; a more specific offer resets the
     * bucket, an equally specific one joins it.
     */
    public function offer(CompiledRoute $compiledRoute, string $routeHost): void
    {
        $specificity = RouteHostPrecedence::specificity($routeHost);

        if ($specificity > $this->specificity) {
            $this->routes = [$compiledRoute];
            $this->specificity = $specificity;

            return;
        }

        if ($specificity !== $this->specificity) {
            return;
        }

        $this->routes[] = $compiledRoute;
    }

    /**
     * @return list<CompiledRoute>
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
