<?php

declare(strict_types=1);

namespace Zef\Framework\Router;

use Closure;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\Routing\CompiledRoute;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RadixTree;
use Zef\Framework\Config\Routing\RouteDsl;
use Zef\Framework\Config\Routing\RoutingRuntime;

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
        private RoutingRuntime $runtime,
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
     * Matches a method and path against the compiled route table.
     */
    public function match(string $method, string $path): MatchResult
    {
        return $this->runtime->match($method, $path);
    }

    /**
     * Resolves a named route, failing closed when the name is not registered.
     */
    public function route(string $name): CompiledRoute
    {
        return $this->runtime->route($name);
    }

    /**
     * Generates a URL from a named route and its parameter values.
     *
     * @param array<string, mixed> $parameters
     */
    public function url(string $name, array $parameters = []): string
    {
        return $this->runtime->url($name, $parameters);
    }

    /**
     * Exposes the one memoised immutable tree for diagnostics and integration.
     */
    public function tree(): RadixTree
    {
        return $this->runtime->tree();
    }
}
