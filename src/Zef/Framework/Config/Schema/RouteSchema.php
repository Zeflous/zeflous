<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Schema;

use Zef\Framework\Config\ConfigException;

/**
 * The Standard Route Schema: the field contract every route entry in a
 * routes configuration must satisfy (roadmap step 1 of the Triad, see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree" -- the
 * "bahasa induk" every DSL output compiles down to before the Radix Tree
 * engine consumes it).
 *
 * The eleven fields and their rules:
 *
 * - `path` (required, non-empty string) -- the URL pattern, e.g.
 *   `/users/{id}`.
 * - `host` (optional, non-empty string) -- a DNS hostname pattern with optional
 *   whole-label parameters or anonymous wildcards, e.g. `api.example.com`,
 *   `{tenant}.example.com` or `*.example.com`.
 * - `version` (optional, non-empty string) -- the API version selected by the
 *   `Accept-Version` or `X-API-Version` request header.
 * - `produces` (optional, non-empty list of media types) -- representations
 *   supported by the route for basic `Accept` negotiation.
 * - `method` (required, non-empty list of non-empty strings) -- the HTTP
 *   methods the route answers, e.g. `['GET', 'POST']`.
 * - `handler` (required, non-empty string) -- the controller/action
 *   reference, e.g. `UserController::class . '@show'`.
 * - `name` (optional, non-empty string) -- the reverse-routing name.
 * - `middleware` (optional, non-empty list of non-empty strings) -- the
 *   middleware attached to the route.
 * - `constraints` (optional, non-empty map of non-empty strings) -- the
 *   parameter rules, e.g. `['id' => '[0-9]+']`.
 * - `meta` (optional, non-empty map) -- free-form metadata (API version,
 *   tenant, locale); the values are intentionally untyped, the shape is not.
 * - `deprecation` (optional, non-empty map) -- the route's deprecation
 *   declaration (RFC 9745 `Deprecation` date, RFC 8594 `Sunset` date, the
 *   deprecation documentation link) every response from the route answers
 *   with; the members' grammar is the routing compiler's judgement
 *   ({@see \Zef\Framework\Config\Routing\RouteDeprecation}), exactly like
 *   host-pattern semantics are.
 *
 * The schema validates *shape*, not HTTP semantics: a lower-case method or a
 * path without a leading slash still passes, because those are policy
 * decisions the engine layer owns. Host-pattern semantics are validated by
 * the routing compiler. Validation is fail-closed (KDD-5): an
 * invalid route is reported with its full path context, never silently
 * dropped, and a key outside the eleven fields is a violation so a typo
 * cannot produce a route that half-exists.
 *
 * The class is a thin definition over the generic {@see ConfigSchema}
 * engine: the rules are data, and the engine owns every check, so a second
 * schema (module configuration, cache manifests) is a new rule map rather
 * than new validation code.
 *
 * @api
 */
final readonly class RouteSchema
{
    /**
     * Field rules in the documentation order above; the order is also the
     * order violations are reported in.
     */
    private const array RULES = [
        'path' => ['required' => true, 'kind' => 'string'],
        'host' => ['required' => false, 'kind' => 'string'],
        'version' => ['required' => false, 'kind' => 'string'],
        'produces' => ['required' => false, 'kind' => 'strings'],
        'method' => ['required' => true, 'kind' => 'strings'],
        'handler' => ['required' => true, 'kind' => 'string'],
        'name' => ['required' => false, 'kind' => 'string'],
        'middleware' => ['required' => false, 'kind' => 'strings'],
        'constraints' => ['required' => false, 'kind' => 'string-map'],
        'meta' => ['required' => false, 'kind' => 'values'],
        'deprecation' => ['required' => false, 'kind' => 'values'],
    ];

    private ConfigSchema $configSchema;

    public function __construct()
    {
        $this->configSchema = new ConfigSchema(self::RULES);
    }

    /**
     * Returns every expectation the route entry fails to satisfy, in
     * deterministic order; a valid entry yields an empty list.
     *
     * @return list<SchemaViolation>
     */
    public function validate(mixed $route): array
    {
        return $this->configSchema->validate($route);
    }

    /**
     * Asserts that the route entry satisfies the Standard Route Schema;
     * otherwise throws a `ConfigException` whose message carries every
     * violation's path context.
     */
    public function assert(mixed $route): void
    {
        $this->configSchema->assert($route);
    }

    /**
     * Asserts that a `routes` configuration value is an array whose every
     * entry satisfies the Standard Route Schema -- the shape a routes file
     * or a cached routes table must have before the engine consumes it.
     *
     * A value that is not an array at all (a scalar, `null`) is rejected
     * with its own message; each entry is then asserted individually, so
     * the first violating entry halts with its full path context. Both a
     * list of entries and a map of named entries validate: the schema
     * governs the entries, not the addressing.
     */
    public function assertList(mixed $routes): void
    {
        if (!\is_array($routes)) {
            throw new ConfigException('The "routes" configuration must be a list of route entries.');
        }

        foreach (array_keys($routes) as $entry) {
            $this->assert($routes[$entry]);
        }
    }
}
