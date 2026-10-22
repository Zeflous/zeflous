<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * A route under declaration: the chainable half of the route DSL.
 *
 * Every {@see RouteDsl} verb returns one of these, already carrying what
 * the declaration site knows -- the parsed {@see RoutePath} (prefixes
 * composed and the grammar applied on the declaration line), the HTTP
 * methods, the handler, and a snapshot of the scope middleware in force
 * where the route was declared. The chained calls then fill in the
 * optional Standard Route Schema fields: `name`, `where` (constraints),
 * route-level `middleware`, and `meta`.
 *
 * The draft is mutable by design and lives for one declaration session;
 * {@see self::toSchemaArray()} freezes it into the plain route entry the
 * configuration system and the radix tree engine consume. The field
 * rules live in {@see RouteDslFields} (declare-once) and
 * {@see RouteDslMiddleware} (the append-only stack).
 *
 * @api
 */
final readonly class PendingRoute
{
    private RouteDslFields $routeDslFields;

    private RouteDslMiddleware $routeDslMiddleware;

    /**
     * @param list<string> $methods         the HTTP methods, already upper-cased by
     *                                      the declaration site
     * @param list<string> $scopeMiddleware the middleware in force where the
     *                                      route was declared
     */
    public function __construct(
        private RoutePath $routePath,
        private array $methods,
        private string $handler,
        array $scopeMiddleware,
    ) {
        $this->routeDslFields = new RouteDslFields();
        $this->routeDslMiddleware = new RouteDslMiddleware($scopeMiddleware);
    }

    /**
     * Declares the reverse-routing name; a second name is an ambiguous
     * declaration, not a rename.
     */
    public function name(string $name): self
    {
        $this->routeDslFields->name($name, $this->routePath);

        return $this;
    }

    /**
     * Declares the parameter constraints: parameter name => pattern. The
     * names must be the route's own parameters; whether a pattern
     * compiles and anchors is the engine's judgement, made once at
     * {@see RadixTree::compile()} time.
     *
     * @param array<array-key, mixed> $constraints
     */
    public function where(array $constraints): self
    {
        $this->routeDslFields->where($constraints, $this->routePath);

        return $this;
    }

    /**
     * Appends route-level middleware, declared after -- and stacked on top
     * of -- whatever scope middleware the route inherited.
     *
     * @param string ...$identifiers middleware identifiers, in declaration order
     */
    public function middleware(string ...$identifiers): self
    {
        $this->routeDslMiddleware->append($this->routePath, ...$identifiers);

        return $this;
    }

    /**
     * Declares the free-form route metadata (API version, tenant, locale);
     * a second declaration is an overwrite in disguise.
     *
     * @param array<array-key, mixed> $meta
     */
    public function meta(array $meta): self
    {
        $this->routeDslFields->meta($meta, $this->routePath);

        return $this;
    }

    /**
     * Declares the route's deprecation -- the RFC 9745 `Deprecation` date,
     * the RFC 8594 `Sunset` date and the deprecation documentation link
     * every response from the route answers with; a second declaration is
     * an overwrite in disguise. The members' grammar is the compile-time
     * judgement of {@see RouteDeprecation}, exactly like a `where`
     * pattern's compilation.
     *
     * @param non-empty-string      $date   the deprecation date (calendar or full ISO-8601 form)
     * @param null|non-empty-string $sunset the sunset date, strictly after the deprecation date
     * @param null|non-empty-string $link   the deprecation documentation URI-reference
     */
    public function deprecated(string $date, ?string $sunset = null, ?string $link = null): self
    {
        $this->routeDslFields->deprecated($date, $sunset, $link, $this->routePath);

        return $this;
    }

    /**
     * Freezes the declaration into a Standard Route Schema entry: the
     * three required fields always, the optional fields only when they
     * were declared, in the schema's own documentation order.
     *
     * @return array<string, mixed>
     */
    public function toSchemaArray(): array
    {
        $entry = [
            'path' => $this->routePath->pattern(),
            'method' => $this->methods,
            'handler' => $this->handler,
        ];

        $declaredName = $this->routeDslFields->declaredName();

        if ($declaredName !== null) {
            $entry['name'] = $declaredName;
        }

        $middleware = $this->routeDslMiddleware->stack();

        if ($middleware !== []) {
            $entry['middleware'] = $middleware;
        }

        $constraints = $this->routeDslFields->declaredConstraints();

        if ($constraints !== null) {
            $entry['constraints'] = $constraints;
        }

        $meta = $this->routeDslFields->declaredMeta();

        if ($meta !== null) {
            $entry['meta'] = $meta;
        }

        $deprecation = $this->routeDslFields->declaredDeprecation();

        if ($deprecation !== null) {
            $entry['deprecation'] = $deprecation;
        }

        return $entry;
    }
}
