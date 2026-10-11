<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The middleware a pending route answers: the scope snapshot it was
 * declared under, followed by the route's own declarations.
 *
 * The stack starts from the middleware in force on the route's
 * declaration line (the roadmap's outer-to-inner order: global, then
 * group) and route-level declarations are appended after it -- stacking
 * is the feature, so unlike the name, constraints, and metadata there is
 * no declare-once rule here.
 *
 * @internal
 */
final class RouteDslMiddleware
{
    /**
     * @param list<string> $middleware the middleware in force where the
     *                                 route was declared
     */
    public function __construct(private array $middleware = [])
    {
    }

    /**
     * Appends route-level middleware, declared after -- and stacked on
     * top of -- the scope middleware the route inherited.
     *
     * @param string ...$identifiers middleware identifiers, in declaration order
     */
    public function append(RoutePath $routePath, string ...$identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if ($identifier === '') {
                throw new ConfigException(
                    \sprintf(
                        'The route "%s" must not declare an empty middleware identifier.',
                        $routePath->pattern(),
                    ),
                );
            }
        }

        foreach ($identifiers as $identifier) {
            $this->middleware[] = $identifier;
        }
    }

    /**
     * The full stack: scope snapshot first, route-level declarations
     * after, in declaration order.
     *
     * @return list<string>
     */
    public function stack(): array
    {
        return $this->middleware;
    }
}
