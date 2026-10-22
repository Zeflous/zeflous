<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

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
     *     version: ?non-empty-string,
     *     produces: list<string>,
     *     method: list<string>,
     *     handler: non-empty-string,
     *     name: ?non-empty-string,
     *     middleware: list<string>,
     *     meta: array<array-key, mixed>,
     *     deprecation: null|array{date: int, sunset: null|int, link: null|non-empty-string},
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
        $host = RouteHostBinding::declared($entry['host'], $routePath);

        return new self(
            $routePath,
            RouteConstraints::fromMap($entry['constraints'], $routePath),
            RouteFields::fromEntry($entry, $host, $routePath),
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
     * Returns captured host parameters, or null when this route does not match.
     * Host-agnostic routes match every host and capture no host parameters.
     *
     * @return null|array<string, string>
     */
    public function hostParameters(?string $host): ?array
    {
        if ($this->fields['host'] === null) {
            return [];
        }

        return RouteHostParameters::parameters($this->fields['host'], RouteHostRequest::canonicalise($host));
    }

    /**
     * The canonical API version this route answers, or null for unversioned routes.
     */
    public function version(): ?string
    {
        return $this->fields['version'];
    }

    /**
     * The media types this route can produce, normalised to lower-case.
     *
     * @return list<string>
     */
    public function produces(): array
    {
        return $this->fields['produces'];
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
     * The route's canonicalised deprecation declaration, or null when the
     * route is not deprecated: the RFC 9745 deprecation instant, the RFC
     * 8594 sunset instant, and the deprecation documentation link the
     * dispatch lane answers with
     * ({@see \Zef\Framework\Middleware\RouteDeprecationHeaders}).
     *
     * @return null|array{date: int, sunset: null|int, link: null|non-empty-string}
     */
    public function deprecation(): ?array
    {
        return $this->fields['deprecation'];
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
}
