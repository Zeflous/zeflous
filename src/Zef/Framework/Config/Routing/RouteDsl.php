<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Closure;

/**
 * The route DSL: the declarative front door of the routing configuration
 * system (see `ROADMAP.md`, "Configuration System + DSL + Radix Tree" --
 * "DSL Layer: Jembatan Developer Experience").
 *
 * A fluent session compiles elegant PHP into exactly what the
 * configuration system already speaks: plain Standard Route Schema
 * entries, the "bahasa induk" every input compiles down to before
 * {@see RadixTree::compile()} consumes it. Nothing is registered to a
 * tree here -- each declaration only fills configuration data:
 *
 * - verbs (`get`, `post`, ... or `match` for a method set) draft a
 *   {@see PendingRoute}, its path composed from the group prefixes in
 *   force and validated by {@see RoutePath::parse()} on the declaration
 *   line;
 * - {@see self::group()} composes a validated {@see RoutePrefix} for the
 *   routes declared inside its closure -- the closure receives this same
 *   façade, and prefix and middleware state are restored when it closes
 *   (a closure that throws leaves no prefix behind);
 * - {@see self::middleware()} declares scope middleware: it attaches to
 *   every route registered after the declaration, and to any group opened
 *   after it, without ever leaking out of the scope it was declared in;
 * - the stack is therefore the roadmap's outer-to-inner order: global,
 *   then group, then the route's own.
 *
 * {@see self::toArray()} freezes the session into a plain route list --
 * schema-shaped output, validated eagerly at every declaration and safe
 * to cache, exactly the shape `config/routes.php` would have returned.
 * The fluent surface lives here; the mutable session state it drives
 * lives in {@see RouteDslScope}.
 *
 * @api
 */
final readonly class RouteDsl
{
    private const string METHOD_GET = 'GET';

    private const string METHOD_HEAD = 'HEAD';

    private const string METHOD_POST = 'POST';

    private const string METHOD_PUT = 'PUT';

    private const string METHOD_PATCH = 'PATCH';

    private const string METHOD_DELETE = 'DELETE';

    private const string METHOD_OPTIONS = 'OPTIONS';

    private RouteDslScope $routeDslScope;

    public function __construct()
    {
        $this->routeDslScope = new RouteDslScope();
    }

    /**
     * Runs a declaration closure against a fresh session and returns the
     * compiled route list -- the one-call form of the DSL.
     *
     * @param Closure(RouteDsl): void $routes the declaration closure
     *
     * @return list<array<string, mixed>> the Standard Route Schema entries
     */
    public static function define(Closure $routes): array
    {
        $routeDsl = new self();
        $routes($routeDsl);

        return $routeDsl->toArray();
    }

    /**
     * Declares a route answering `GET`.
     */
    public function get(string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft([self::METHOD_GET], $path, $handler);
    }

    /**
     * Declares a route answering `HEAD`.
     */
    public function head(string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft([self::METHOD_HEAD], $path, $handler);
    }

    /**
     * Declares a route answering `POST`.
     */
    public function post(string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft([self::METHOD_POST], $path, $handler);
    }

    /**
     * Declares a route answering `PUT`.
     */
    public function put(string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft([self::METHOD_PUT], $path, $handler);
    }

    /**
     * Declares a route answering `PATCH`.
     */
    public function patch(string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft([self::METHOD_PATCH], $path, $handler);
    }

    /**
     * Declares a route answering `DELETE`.
     */
    public function delete(string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft([self::METHOD_DELETE], $path, $handler);
    }

    /**
     * Declares a route answering `OPTIONS`.
     */
    public function options(string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft([self::METHOD_OPTIONS], $path, $handler);
    }

    /**
     * Declares a route answering a set of HTTP methods; each spelling is
     * upper-cased once at the declaration site (the engine still owns
     * de-duplication and ordering).
     *
     * @param list<mixed> $methods the HTTP methods, any spelling
     */
    public function match(array $methods, string $path, string $handler): PendingRoute
    {
        return $this->routeDslScope->draft(RouteDslMethods::upperCased($methods, $path), $path, $handler);
    }

    /**
     * Opens a group: routes declared inside the closure carry the
     * composed prefix, and the middleware in force when the group opened.
     *
     * The closure receives this same façade (nested groups nest their
     * prefixes the same way); when it returns -- or throws -- the prefix
     * and middleware state are restored, so a group never leaks outward.
     *
     * @param Closure(RouteDsl): void $routes the declaration closure
     */
    public function group(string $prefix, Closure $routes): static
    {
        $routeDslSnapshot = $this->routeDslScope->openGroup($prefix);

        try {
            $routes($this);
        } finally {
            $this->routeDslScope->closeGroup($routeDslSnapshot);
        }

        return $this;
    }

    /**
     * Declares scope middleware: it attaches to every route registered
     * after this declaration (and to groups opened after it), in
     * outer-to-inner order with the route's own middleware last.
     *
     * @param string ...$identifiers middleware identifiers, in declaration order
     */
    public function middleware(string ...$identifiers): static
    {
        $this->routeDslScope->middleware(...$identifiers);

        return $this;
    }

    /**
     * Freezes the declaration session into its route list; the session
     * itself stays usable, and a second call snapshots the same way.
     *
     * @return list<array<string, mixed>> the Standard Route Schema entries
     */
    public function toArray(): array
    {
        return $this->routeDslScope->frozenRoutes();
    }
}
