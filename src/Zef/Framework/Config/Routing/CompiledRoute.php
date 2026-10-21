<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * One route entry, validated and normalised for the radix tree engine.
 *
 * This is the engine's consumption view of the Standard Route Schema (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree"): the "bahasa
 * induk" every DSL output compiles down to. {@see self::fromArray()} is
 * the single front door -- a raw entry is shape-checked by
 * {@see RouteEntryShape}, the path is parsed by {@see RoutePath}, and the
 * constraints are validated and anchored by {@see RouteConstraints}, all
 * fail-closed, before any route exists.
 *
 * Normalisation is deliberate and minimal: methods are upper-cased once
 * (request methods arrive in any case), de-duplicated by their upper-case
 * spelling (a repeated method is an ambiguous declaration, not a merge),
 * and sorted, so the method-not-allowed answer is deterministic. The
 * original pattern is kept verbatim for diagnostics and reverse routing.
 *
 * @api
 */
final readonly class CompiledRoute
{
    /**
     * @param array{
     *     host: ?non-empty-string,
     *     method: list<string>,
     *     handler: non-empty-string,
     *     name: ?non-empty-string,
     *     middleware: list<string>,
     *     meta: array<array-key, mixed>,
     * } $fields
     */
    private function __construct(
        private RoutePath $routePath,
        private RouteConstraints $routeConstraints,
        private array $fields,
    ) {
    }

    /**
     * Compiles one raw route entry into a validated route.
     *
     * @param mixed $route a Standard Route Schema entry
     */
    public static function fromArray(mixed $route): self
    {
        $entry = RouteEntryShape::fields($route);
        $routePath = RoutePath::parse($entry['path']);

        return new self(
            $routePath,
            RouteConstraints::fromMap($entry['constraints'], $routePath),
            [
                'host' => $entry['host'] === null ? null : RouteHost::canonical($entry['host']),
                'method' => self::normalizedMethods($entry['method']),
                'handler' => $entry['handler'],
                'name' => $entry['name'],
                'middleware' => $entry['middleware'],
                'meta' => $entry['meta'],
            ],
        );
    }

    /**
     * The parsed route path pattern.
     */
    public function path(): RoutePath
    {
        return $this->routePath;
    }

    /**
     * The exact host this route answers, or null for a host-agnostic route.
     */
    public function host(): ?string
    {
        return $this->fields['host'];
    }

    /**
     * Whether this route belongs to the supplied canonical request host.
     *
     * Host-agnostic routes are fallback candidates; the tree prefers an exact
     * host candidate when one exists for the matched path.
     */
    public function matchesHost(?string $host): bool
    {
        return $this->fields['host'] === null || $this->fields['host'] === $host;
    }

    /**
     * The HTTP methods this route answers, upper-cased and sorted.
     *
     * @return list<string>
     */
    public function methods(): array
    {
        return $this->fields['method'];
    }

    /**
     * The controller/action reference.
     */
    public function handler(): string
    {
        return $this->fields['handler'];
    }

    /**
     * The reverse-routing name, or null when the route is unnamed.
     */
    public function name(): ?string
    {
        return $this->fields['name'];
    }

    /**
     * The middleware attached to the route, in declaration order.
     *
     * @return list<string>
     */
    public function middleware(): array
    {
        return $this->fields['middleware'];
    }

    /**
     * The validated, anchored parameter constraints.
     */
    public function constraints(): RouteConstraints
    {
        return $this->routeConstraints;
    }

    /**
     * The free-form metadata map.
     *
     * @return array<array-key, mixed>
     */
    public function meta(): array
    {
        return $this->fields['meta'];
    }

    /**
     * Whether the route answers the request method (both spellings are
     * upper-cased once, so case never decides a match).
     */
    public function answersMethod(string $method): bool
    {
        return \in_array(mb_strtoupper($method), $this->fields['method'], true);
    }

    /**
     * Whether the captured segment values satisfy the route's parameter
     * constraints, in path order: the engine's last judgement before a
     * walked branch becomes a match.
     *
     * @param list<string> $captured one value per route parameter
     */
    public function accepts(array $captured): bool
    {
        foreach ($this->routePath->parameterNames() as $index => $name) {
            if ($this->routeConstraints->check($name, $captured[$index])) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Upper-cases, de-duplicates and sorts a method list; `sort()`
     * re-indexes the map into a list, so the returned methods are keyed 0..n.
     *
     * @param list<string> $methods
     *
     * @return list<string>
     */
    private static function normalizedMethods(array $methods): array
    {
        $upperCased = [];

        foreach ($methods as $method) {
            $upper = mb_strtoupper($method);

            if (isset($upperCased[$upper])) {
                throw new ConfigException(
                    \sprintf('The route entry field "method" must not repeat the method "%s".', $upper),
                );
            }

            $upperCased[$upper] = $upper;
        }

        sort($upperCased, \SORT_STRING);

        return $upperCased;
    }
}
