<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The parameterised-host bucket of a selection round: routes whose host
 * pattern matched with captures, keeping only the offers of the highest
 * specificity seen so far.
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
