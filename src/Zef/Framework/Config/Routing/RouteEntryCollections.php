<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Validates the value rules of the route entry's non-path fields: single
 * non-empty strings, the method and middleware lists, and the constraints
 * map.
 *
 * {@see RouteEntryShape} orchestrates the whole entry (the entry itself
 * must be an array, its keys must be known fields) and delegates here for
 * every field's value; the free-form meta field is judged by
 * {@see RouteEntryMeta}, because it is not a string collection. The
 * dependency stays one-way, because the value rules need nothing from
 * the orchestration. Absent optional fields default to their empty form; a
 * *present* but empty or wrongly shaped collection is always a violation,
 * because "explicitly empty" and "not given" mean different things to the
 * route table. Every violation raises a {@see ConfigException} naming the
 * field.
 *
 * @internal
 */
final class RouteEntryCollections
{
    /**
     * The constraints field name: the single spelling the map's rules,
     * lookups and diagnostics share.
     */
    private const string CONSTRAINTS = 'constraints';

    /**
     * Narrows one field value to a non-empty string; the list and map
     * rules reuse this for their items.
     *
     * @return non-empty-string
     */
    public static function nonEmptyString(mixed $value, string $field): string
    {
        if (!\is_string($value)) {
            throw new ConfigException(
                \sprintf('The route entry field "%s" must be a string, got %s.', $field, get_debug_type($value)),
            );
        }

        if ($value === '') {
            throw new ConfigException(
                \sprintf('The route entry field "%s" must not be empty.', $field),
            );
        }

        return $value;
    }

    /**
     * A field holding a list of non-empty strings; required lists must be
     * present, optional lists default to an empty list.
     *
     * @param array<array-key, mixed> $route
     *
     * @return list<string>
     */
    public static function stringList(array $route, string $field, bool $required): array
    {
        if (!\array_key_exists($field, $route)) {
            if ($required) {
                throw new ConfigException(
                    \sprintf('The route entry field "%s" is required.', $field),
                );
            }

            return [];
        }

        $value = $route[$field];

        if (!\is_array($value) || !array_is_list($value)) {
            throw new ConfigException(
                \sprintf('The route entry field "%s" must be a list of non-empty strings.', $field),
            );
        }

        if ($value === []) {
            throw new ConfigException(
                \sprintf('The route entry field "%s" must not be empty.', $field),
            );
        }

        return array_map(
            static fn (mixed $item): string => self::nonEmptyString($item, $field),
            $value,
        );
    }

    /**
     * The optional constraints field: a map of parameter names to
     * non-empty pattern strings.
     *
     * @param array<array-key, mixed> $route
     *
     * @return array<string, non-empty-string>
     */
    public static function stringMap(array $route): array
    {
        if (!\array_key_exists(self::CONSTRAINTS, $route)) {
            return [];
        }

        $value = $route[self::CONSTRAINTS];

        if ($value === []) {
            throw new ConfigException(
                'The route entry field "constraints" must not be empty.',
            );
        }

        if (!\is_array($value) || array_is_list($value)) {
            throw new ConfigException(
                'The route entry field "constraints" must be a map of parameter names to patterns.',
            );
        }

        $patterns = [];

        foreach (array_keys($value) as $name) {
            if (!\is_string($name)) {
                throw new ConfigException(
                    'The route entry field "constraints" must use parameter names as keys.',
                );
            }

            $patterns[$name] = self::nonEmptyString($value[$name], self::CONSTRAINTS);
        }

        return $patterns;
    }
}
