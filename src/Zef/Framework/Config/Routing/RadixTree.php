<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The compiled radix tree: the engine of the router's matching layer.
 *
 * {@see self::compile()} turns a route table (a list of Standard Route
 * Schema entries) into an immutable tree: each entry is validated into a
 * {@see CompiledRoute}, method overlaps are rejected by
 * {@see RouteTableRegistry}, and {@see RouteTreeBuilder} stores every
 * pattern's static runs once (shared prefixes split and merge) with
 * anonymous parameter edges between them. Nothing is matched, validated
 * or repaired at match time -- the tree is the roadmap's "makanan siap
 * saji": plain, pre-judged data.
 *
 * {@see self::match()} delegates the pipeline to {@see RouteMatchPipeline}:
 * the path walk settles candidates, then the answer narrows by exact
 * host before judging the method: a matching exact host takes
 * precedence over host-agnostic routes, and a method mismatch on
 * that exact host remains 405 rather than falling through. The walk
 * settles routes whose pattern ended the path and whose constraints
 * accept captured values, and {@see RouteMethodAnswer} selects the
 * one answering the request method (the request method is
 * upper-cased once, by {@see CompiledRoute::answersMethod()} -- the
 * single owner of the case policy). A path that matched while no
 * settled route answers the request method yields the
 * method-not-allowed result carrying the sorted, de-duplicated
 * union of the methods those routes do answer. Match-time inputs
 * never raise: a hostile URL is runtime data, not a defect in the
 * route table (that is what {@see ConfigException} is reserved
 * for).
 *
 * A request path that is empty or lacks the leading slash is structurally
 * unreachable: the root node carries no route (every pattern contributes
 * at least the `/` run) and every root edge begins with a slash, so such
 * a request cannot reach any route.
 *
 * @api
 */
final readonly class RadixTree
{
    /**
     * @param array<string, CompiledRoute> $namedRoutes the reverse-routing index
     * @param list<CompiledRoute>          $routes      the compiled entries, in the table's own declaration order
     */
    public function __construct(
        private RadixNode $radixNode,
        private array $namedRoutes = [],
        private array $routes = [],
    ) {
    }

    /**
     * Compiles a route table into a radix tree, assembling itself
     * over the parts {@see RouteTableCompiler} answers (the list
     * check, the one fail-closed {@see CompiledRoute::fromArray()}
     * per entry, the {@see RouteTreeBuilder} wiring) -- the tree owns
     * the assembly, the compiler the orchestration.
     *
     * @param array<mixed> $routes a list of Standard Route Schema entries
     */
    public static function compile(array $routes): self
    {
        $compiledRoutes = RouteTableCompiler::compile($routes);

        return new self(
            $compiledRoutes['root'],
            $compiledRoutes['namedRoutes'],
            $compiledRoutes['routes'],
        );
    }

    /**
     * The compiled entries, in the table's own declaration order --
     * the inspection seam the CLI analysis commands render ({@see
     * RouteTreeCache}'s artifact carries the entries in this same
     * order, so a cached boot answers the identical list). The tree
     * answers its own compiled route set; a caller that re-derives
     * the list from raw entries would be a second parse, never this.
     *
     * @return list<CompiledRoute>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * The tree's own node structure as plain depth-first rows -- the
     * debugging seam the CLI's tree dump renders: one row per node
     * (the depth, the edge kind, the node's prefix, the patterns of
     * the routes settled on it), static children first and the
     * parameter edge last, in the engine's own build order. The
     * shape is a description, not a cache: nothing rehydrates from
     * it.
     *
     * @return list<array{depth: int, edge: string, prefix: string, routes: list<string>}>
     */
    public function structure(): array
    {
        return RouteTreeStructure::rows($this->radixNode);
    }

    /**
     * The route registered under a name -- the reverse-routing entry
     * point. A name nothing was registered under is a defect in the
     * caller's table of names, so it fails closed with the name in the
     * message.
     */
    public function route(string $name): CompiledRoute
    {
        return $this->namedRoutes[$name]
            ?? throw new ConfigException(\sprintf('The route name "%s" is not registered.', $name));
    }

    /**
     * Matches a request method, path and optional exact host against
     * the tree: the pipeline fold {@see RouteMatchPipeline} runs the
     * engine's own order (the path walk settles candidates, {@see
     * RouteHostSelection} picks the highest-precedence host bucket,
     * {@see RouteVersionSelection} narrows by API version, and {@see
     * RouteMethodAnswer} judges the method) -- the counting twin of
     * the same sequence is the development surface {@see
     * self::explain()} answers.
     */
    public function match(
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): MatchResult {
        return RouteMatchPipeline::match($this->radixNode, $method, $path, $host, $accept, $version);
    }

    /**
     * Explains the match of a request against the tree: the same
     * pipeline {@see self::match()} runs, staged and counted, so the
     * caller can say why the table refused the request -- the
     * development surface of the engine, answering a {@see
     * RouteMatchExplanation} whose outcome is the match's own.
     */
    public function explain(
        string $method,
        string $path,
        ?string $host = null,
        ?string $accept = null,
        ?string $version = null,
    ): RouteMatchExplanation {
        return RouteMatchExplainer::explain($this->radixNode, $method, $path, $host, $accept, $version);
    }
}
