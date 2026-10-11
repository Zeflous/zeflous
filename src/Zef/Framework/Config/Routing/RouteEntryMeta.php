<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The optional meta field of one route entry: a non-empty map of
 * free-form values (version, tenant, locale -- see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree").
 *
 * The meta values are deliberately unvalidated: they carry declarations
 * the route table must preserve verbatim, not data the engine
 * interprets. Only the *shape* is judged -- present but not a map, or
 * present and empty, is a violation, exactly like every other route
 * entry field ("explicitly empty" and "not given" mean different things
 * to the route table).
 *
 * @internal
 */
final class RouteEntryMeta
{
    /**
     * @param array<array-key, mixed> $route
     *
     * @return array<array-key, mixed>
     */
    public static function fromRoute(array $route): array
    {
        if (!\array_key_exists('meta', $route)) {
            return [];
        }

        $value = $route['meta'];

        if (!\is_array($value)) {
            throw new ConfigException(
                \sprintf('The route entry field "meta" must be a map, got %s.', get_debug_type($value)),
            );
        }

        if ($value === []) {
            throw new ConfigException(
                'The route entry field "meta" must not be empty.',
            );
        }

        return $value;
    }
}
