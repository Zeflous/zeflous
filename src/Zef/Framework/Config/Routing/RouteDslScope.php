<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The declaration session's scope state: the prefix in force (every open
 * group's contribution, composed), the scope middleware flowing forward
 * to subsequent registrations, and the drafts declared so far.
 *
 * This is the mutable half of {@see RouteDsl} -- the façade owns the
 * fluent surface, the scope owns what that surface mutates: groups push
 * a composed {@see RoutePrefix} and restore a {@see RouteDslSnapshot} on
 * close (normal or thrown), middleware declared in a scope attaches to
 * every later registration in it without ever escaping its group, and
 * every draft snapshots the middleware in force on its declaration line.
 *
 * @internal
 */
final class RouteDslScope
{
    private RoutePrefix $routePrefix;

    /** @var list<string> */
    private array $scopeMiddleware = [];

    /** @var list<PendingRoute> */
    private array $pendingRoutes = [];

    public function __construct()
    {
        $this->routePrefix = RoutePrefix::fromString('/');
    }

    /**
     * Declares scope middleware: it attaches to every route registered
     * after the declaration, and to any group opened after it.
     *
     * @param string ...$identifiers middleware identifiers, in declaration order
     */
    public function middleware(string ...$identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if ($identifier === '') {
                throw new ConfigException('The middleware identifier must not be empty.');
            }
        }

        foreach ($identifiers as $identifier) {
            $this->scopeMiddleware[] = $identifier;
        }
    }

    /**
     * Opens a group: composes the declared prefix onto the prefix in
     * force and returns the state to restore when the group closes.
     *
     * The declared prefix is validated first, on its own spelling, so
     * the failure names what the developer wrote rather than the
     * composition it would have produced.
     */
    public function openGroup(string $prefix): RouteDslSnapshot
    {
        RoutePrefix::fromString($prefix);

        $routeDslSnapshot = new RouteDslSnapshot($this->routePrefix, $this->scopeMiddleware);
        $this->routePrefix = RoutePrefix::fromString($this->routePrefix->concat($prefix));

        return $routeDslSnapshot;
    }

    /**
     * Closes a group by restoring the state its snapshot captured.
     */
    public function closeGroup(RouteDslSnapshot $routeDslSnapshot): void
    {
        $this->routePrefix = $routeDslSnapshot->routePrefix;
        $this->scopeMiddleware = $routeDslSnapshot->scopeMiddleware;
    }

    /**
     * Drafts a route: the path is composed from the prefix in force and
     * validated by the grammar on the declaration line, and the scope
     * middleware in force is snapshotted into the draft.
     *
     * @param list<string> $methods
     */
    public function draft(array $methods, string $path, string $handler): PendingRoute
    {
        if ($handler === '') {
            throw new ConfigException('The route handler must not be empty.');
        }

        $pendingRoute = new PendingRoute(
            RoutePath::parse($this->routePrefix->concat($path)),
            $methods,
            $handler,
            $this->scopeMiddleware,
        );
        $this->pendingRoutes[] = $pendingRoute;

        return $pendingRoute;
    }

    /**
     * Freezes every declared draft into its Standard Route Schema entry,
     * in declaration order -- the session's compiled output.
     *
     * @return list<array<string, mixed>>
     */
    public function frozenRoutes(): array
    {
        $routes = [];

        foreach ($this->pendingRoutes as $pendingRoute) {
            $routes[] = $pendingRoute->toSchemaArray();
        }

        return $routes;
    }
}
