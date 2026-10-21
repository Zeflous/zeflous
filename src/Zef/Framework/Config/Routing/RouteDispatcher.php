<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Container\Container;

/**
 * Executes matched routes -- the Triad's runtime endpoint (see
 * `ROADMAP.md`, "Roadmap Implementasi Gabungan", step 5: Loader ->
 * Engine -> Dispatcher).
 *
 * `dispatch()` matches the request through the {@see RoutingRuntime} the
 * dispatcher was built with and, when a route is found, resolves its
 * handler through the container and invokes it with the captured
 * parameters (one argument: the parameter map keyed by name). The handler
 * contract is deliberately the routing layer's own -- a callable that
 * takes the parameter map and returns whatever it likes -- because the
 * HTTP layer (request -> response handlers, middleware execution) is a
 * separate roadmap area; this dispatcher closes the configuration
 * system's integration seam, not the HTTP one.
 *
 * A route's middleware list is NOT executed here: middleware pipelines are
 * the Middleware Pipeline roadmap area. It is not silently dropped either
 * -- the matched route travels on the executed outcome
 * ({@see DispatchOutcome::route()}), so its `middleware()` (and `meta()`)
 * stay readable by whatever layer takes the dispatch result forward.
 *
 * Everything fail-closed happens before any handler runs: an unknown
 * handler service, a service that is not callable, or a `Class@method`
 * pair whose class or method the container cannot satisfy, all raise a
 * `ConfigException` with the handler in the message (the resolution rules
 * live in {@see RouteHandlerResolver}).
 *
 * @api
 */
final readonly class RouteDispatcher
{
    private RouteHandlerResolver $routeHandlerResolver;

    public function __construct(
        private RoutingRuntime $routingRuntime,
        Container $container,
    ) {
        $this->routeHandlerResolver = new RouteHandlerResolver($container);
    }

    /**
     * Dispatches one request: match, resolve, execute.
     */
    public function dispatch(
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): DispatchOutcome {
        return $this->dispatchMatch($this->routingRuntime->match($method, $path, $host, $accept, $version));
    }

    /**
     * Executes a previously computed match so an HTTP adapter can compose route
     * middleware without walking the radix tree twice.
     *
     * @internal
     */
    public function dispatchMatch(MatchResult $matchResult): DispatchOutcome
    {
        if (!$matchResult->isFound()) {
            if ($matchResult->isNotAcceptable()) {
                return DispatchOutcome::notAcceptable($matchResult->acceptableMediaTypes());
            }

            return $matchResult->isMethodNotAllowed()
                ? DispatchOutcome::methodNotAllowed($matchResult->allowedMethods())
                : DispatchOutcome::notFound();
        }

        $route = $matchResult->route();
        $parameters = $matchResult->parameters();
        $routeHandler = $this->routeHandlerResolver->resolve($route->handler());

        return DispatchOutcome::executed($route, $parameters, $routeHandler($parameters));
    }
}
