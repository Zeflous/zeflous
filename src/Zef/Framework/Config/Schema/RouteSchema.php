<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Schema;

/**
 * The Standard Route Schema: the field contract every route entry in a
 * routes configuration must satisfy (roadmap step 1 of the Triad, see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree" -- the
 * "bahasa induk" every DSL output compiles down to before the Radix Tree
 * engine consumes it).
 *
 * The seven fields and their rules:
 *
 * - `path` (required, non-empty string) -- the URL pattern, e.g.
 *   `/users/{id}`.
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
 *
 * The schema validates *shape*, not HTTP semantics: a lower-case method or a
 * path without a leading slash still passes, because those are policy
 * decisions the engine layer owns. Validation is fail-closed (KDD-5): an
 * invalid route is reported with its full path context, never silently
 * dropped, and a key outside the seven fields is a violation so a typo
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
        'method' => ['required' => true, 'kind' => 'strings'],
        'handler' => ['required' => true, 'kind' => 'string'],
        'name' => ['required' => false, 'kind' => 'string'],
        'middleware' => ['required' => false, 'kind' => 'strings'],
        'constraints' => ['required' => false, 'kind' => 'string-map'],
        'meta' => ['required' => false, 'kind' => 'values'],
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
}
