<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The routing runtime over a frozen configuration repository -- the Triad's
 * fifth and final seam (see `ROADMAP.md`, "Roadmap Implementasi Gabungan",
 * step 5: Loader -> Engine -> Dispatcher).
 *
 * `fromConfig()` reads the route table the `ConfigBootstrapper` pipeline has
 * already loaded, merged, schema-validated and (on a cache hit) served from
 * the compiled cache. The engine's radix tree is then compiled from that
 * table AT MOST ONCE per instance -- lazily, on first use -- and memoised:
 * the roadmap's compile-once-per-worker economics. A fresh instance is the
 * hot-reload path (`fromConfig()` again over a rebuilt repository); a live
 * runtime is never re-read or re-compiled, and nothing it exposes can
 * mutate the tree it answers from.
 *
 * The class is deliberately not `final readonly`: the memoised tree is
 * mutable-by-design internal state, the precedent being `RouteDslScope`
 * (a mutable session producing frozen results). Everything observable
 * through the public surface is frozen -- `match()` answers from the one
 * immutable tree, and `route()` / `url()` resolve through that same tree's
 * compile-time name index (the URL generator is a stateless value object
 * over the tree, so `url()` builds one per call and keeps the runtime's
 * mutable surface down to the single memoised compilation).
 *
 * Addressing follows `RouteSchema::assertList()`: a `routes` value may be a
 * LIST of entries or a MAP of named entries ("the schema governs the
 * entries, not the addressing"). The map's keys are addressing sugar and
 * are discarded here -- a route's name is the entry's own `name` field, one
 * source of truth, the engine's compile-time name index. A missing key is a
 * valid zero-routing table; an explicit non-array (a scalar, `null`) is a
 * defect in the configuration and fails closed with the schema layer's own
 * message. The entries themselves are not re-validated: the bootstrapper's
 * schema validation owns that in the normal pipeline, and the engine's
 * `CompiledRoute::fromArray()` remains the fail-closed net over any
 * repository that skipped it.
 *
 * @api
 */
final class RoutingRuntime
{
    private ?RadixTree $radixTree = null;

    /**
     * @param list<mixed> $routes the route entries in declaration order
     */
    private function __construct(
        private readonly array $routes,
    ) {
    }

    /**
     * Builds the runtime over a frozen configuration repository.
     *
     * A repository without a `routes` key yields the zero-routing runtime
     * (every match is not-found); a `routes` value that is not an array at
     * all fails closed.
     */
    public static function fromConfig(Config $config): self
    {
        if (!$config->has('routes')) {
            return new self([]);
        }

        $routes = $config->get('routes');
        if (!\is_array($routes)) {
            throw new ConfigException('The "routes" configuration must be a list of route entries.');
        }

        return new self(array_values($routes));
    }

    /**
     * Builds the runtime over an already-compiled tree -- the compiled
     * cache's boot path ({@see RouteTreeCache::load()}), where the
     * engine's compilation was paid at build time and the boot skips it
     * entirely. The tree is adopted as the instance's one memoised
     * compilation: everything the runtime answers comes from it, exactly
     * like a tree compiled lazily.
     */
    public static function fromTree(RadixTree $radixTree): self
    {
        $runtime = new self([]);
        $runtime->radixTree = $radixTree;

        return $runtime;
    }

    /**
     * The compiled radix tree -- built at most once per instance, on the
     * first call, and memoised afterwards.
     */
    public function tree(): RadixTree
    {
        $this->radixTree ??= RadixTree::compile($this->routes);

        return $this->radixTree;
    }

    /**
     * Matches a request method and path against the compiled tree.
     *
     * The method travels raw: upper-casing is the engine's one policy point
     * (`CompiledRoute::answersMethod()`), so unicode request methods round-trip
     * unchanged.
     */
    public function match(
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): MatchResult {
        return $this->tree()->match($method, $path, $host, $accept, $version);
    }

    /**
     * The route registered under a name -- the reverse-routing entry point,
     * failing closed on a name nothing was registered under.
     */
    public function route(string $name): CompiledRoute
    {
        return $this->tree()->route($name);
    }

    /**
     * Generates an absolute URL for a named route with a declared host pattern.
     *
     * @param array<string, mixed> $parameters
     */
    public function absoluteUrl(string $name, array $parameters, string $scheme = 'https'): string
    {
        return new RouteUrlGenerator($this->tree())->generateAbsolute($name, $parameters, $scheme);
    }

    /**
     * Generates the URL of a named route over the same compiled tree,
     * failing closed on any violation the generator itself rejects.
     *
     * @param array<string, mixed> $parameters
     */
    public function url(string $name, array $parameters): string
    {
        return new RouteUrlGenerator($this->tree())->generate($name, $parameters);
    }
}
