<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The compiled radix tree: the engine of the router's matching layer.
 *
 * {@see self::compile()} turns a route table (a list of Standard Route
 * Schema entries) into an immutable tree: each entry is validated into a
 * {@see CompiledRoute}, duplicates are rejected by
 * {@see RouteTableRegistry}, and {@see RouteTreeBuilder} stores every
 * pattern's static runs once (shared prefixes split and merge) with
 * anonymous parameter edges between them. Nothing is matched, validated
 * or repaired at match time -- the tree is the roadmap's "makanan siap
 * saji": plain, pre-judged data.
 *
 * {@see self::match()} delegates the walk to {@see RouteMatcher} and
 * judges only the method: the request method is upper-cased once, by
 * {@see CompiledRoute::answersMethod()} (the single owner of the case
 * policy), and a path that matches a route whose methods exclude the
 * request method yields the method-not-allowed result carrying the
 * sorted methods it does answer. Match-time inputs never raise: a
 * hostile URL is runtime data, not a defect in the route table (that is
 * what {@see ConfigException} is reserved for).
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
    public function __construct(private RadixNode $radixNode)
    {
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

        return new self(new RouteTreeBuilder()->build($compiledRoutes));
    }

    /**
     * Matches a request method and path against the tree.
     */
    public function match(string $method, string $path): MatchResult
    {
        $captured = [];
        $route = new RouteMatcher()->walk($this->radixNode, $path, 0, $captured);

        if (!$route instanceof CompiledRoute) {
            return MatchResult::notFound();
        }

        if (!$route->answersMethod($method)) {
            return MatchResult::methodNotAllowed($route->methods());
        }

        return MatchResult::found($route, $this->parameters($route, $captured));
    }

    /**
     * Zips the route's parameter names with the captured segment values.
     *
     * @param list<string> $captured
     *
     * @return array<string, string>
     */
    private function parameters(CompiledRoute $compiledRoute, array $captured): array
    {
        $parameters = [];

        foreach ($compiledRoute->path()->parameterNames() as $index => $name) {
            $parameters[$name] = $captured[$index];
        }

        return $parameters;
    }
}
