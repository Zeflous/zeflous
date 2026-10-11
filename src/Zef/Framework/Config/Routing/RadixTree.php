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
 * {@see self::match()} delegates the walk to {@see RouteMatcher} and
 * judges only the method: the walk settles the routes whose pattern
 * ended the path and whose constraints accept the captured values, and
 * {@see RouteMethodAnswer} selects the one answering the request method
 * (the request method is upper-cased once, by
 * {@see CompiledRoute::answersMethod()} -- the single owner of the case
 * policy). A path that matched while no settled route answers the
 * request method yields the method-not-allowed result carrying the
 * sorted, de-duplicated union of the methods those routes do answer.
 * Match-time inputs never raise: a hostile URL is runtime data, not a
 * defect in the route table (that is what {@see ConfigException} is
 * reserved for).
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
     */
    public function __construct(
        private RadixNode $radixNode,
        private array $namedRoutes = [],
    ) {
    }

    /**
     * Compiles a route table into a radix tree.
     *
     * @param array<mixed> $routes a list of Standard Route Schema entries
     */
    public static function compile(array $routes): self
    {
        if (!array_is_list($routes)) {
            throw new ConfigException('The route table must be a list of route entries.');
        }

        $compiledRoutes = [];

        foreach (array_keys($routes) as $index) {
            $compiledRoutes[] = CompiledRoute::fromArray($routes[$index]);
        }

        $routeTreeBuilder = new RouteTreeBuilder();

        return new self(
            $routeTreeBuilder->build($compiledRoutes),
            $routeTreeBuilder->registry()->namedRoutes(),
        );
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
     * Matches a request method and path against the tree.
     */
    public function match(string $method, string $path): MatchResult
    {
        $captured = [];
        $routes = new RouteMatcher()->walk($this->radixNode, $path, 0, $captured);

        if ($routes === []) {
            return MatchResult::notFound();
        }

        return RouteMethodAnswer::resolve($method, $routes, $captured);
    }
}
