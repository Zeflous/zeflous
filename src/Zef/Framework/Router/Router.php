<?php

declare(strict_types=1);

namespace Zef\Framework\Router;

use Closure;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteDispatcher;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Config\Routing\RouteTreeCache;
use Zef\Framework\Config\Routing\RoutingRuntime;
use Zef\Framework\Container\Container;

/**
 * Public router facade over the configuration layer's compiled radix engine.
 *
 * The enterprise Router surface is deliberately an adapter: the Config
 * Routing engine remains the single owner of route grammar, conflict
 * detection, matching semantics and reverse-routing validation. This class
 * provides one stable entry point for config-backed and DSL-backed route
 * tables without copying or rebuilding the engine's implementation.
 *
 * A Router instance owns one RoutingRuntime, which compiles its immutable
 * radix tree lazily and memoises it for the lifetime of the instance. Create
 * a new Router when the application has rebuilt its route configuration.
 *
 * @api
 */
final readonly class Router
{
    private function __construct(
        private RoutingRuntime $routingRuntime,
    ) {
    }

    /**
     * Builds a router over an already-loaded configuration repository.
     *
     * The repository's `routes` entry remains the source of truth and is
     * validated by the configuration routing runtime and compiled engine.
     */
    public static function fromConfig(Config $config): self
    {
        return new self(RoutingRuntime::fromConfig($config));
    }

    /**
     * Builds a router from Standard Route Schema entries.
     *
     * @param array<array-key, mixed> $routes route entries, in declaration order
     */
    public static function fromRoutes(array $routes): self
    {
        return self::fromConfig(new Config(['routes' => $routes]));
    }

    /**
     * Compiles a route DSL closure and builds a router from its declarations.
     *
     * @param Closure(RouteDsl): void $routes
     */
    public static function define(Closure $routes): self
    {
        return self::fromRoutes(RouteDsl::define($routes));
    }

    /**
     * Builds a router over a compiled route tree cache -- the production
     * boot path: the artifact is loaded, not compiled, and the returned
     * router answers from the rehydrated immutable tree over the same
     * memoised runtime surface.
     */
    public static function fromCache(RouteTreeCache $routeTreeCache): self
    {
        return new self(RoutingRuntime::fromTree($routeTreeCache->load()));
    }

    /**
     * Creates the existing container-backed dispatcher over this router's
     * memoized runtime. Keep the returned dispatcher for repeated dispatches
     * rather than constructing one per request.
     */
    public function dispatcher(Container $container): RouteDispatcher
    {
        return new RouteDispatcher($this->routingRuntime, $container);
    }

    /**
     * Matches a method and path against the compiled route table.
     */
    public function match(
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): MatchResult {
        return $this->routingRuntime->match($method, $path, $host, $accept, $version);
    }

    /**
     * Resolves a named route, failing closed when the name is not registered.
     */
    public function route(string $name): CompiledRoute
    {
        return $this->routingRuntime->route($name);
    }

    /**
     * Generates a URL from a named route and its parameter values.
     *
     * @param array<string, mixed> $parameters
     */
    public function url(string $name, array $parameters = []): string
    {
        return $this->routingRuntime->url($name, $parameters);
    }

    /**
     * Generates an absolute URL for a named route that declares a host pattern.
     *
     * @param array<string, mixed> $parameters
     */
    public function absoluteUrl(string $name, array $parameters, string $scheme = 'https'): string
    {
        return $this->routingRuntime->absoluteUrl($name, $parameters, $scheme);
    }

    /**
     * Exposes the one memoised immutable tree for diagnostics and integration.
     */
    public function tree(): RadixTree
    {
        return $this->routingRuntime->tree();
    }
}
