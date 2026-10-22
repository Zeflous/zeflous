<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Validates the shape of one raw route entry (the Standard Route Schema of
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree") before the
 * engine builds anything from it.
 *
 * The engine does not depend on the configuration layer that validates the
 * same schema at bootstrap: this is the defensive, self-contained half of
 * the contract -- a route table compiled from hardcoded arrays must fail
 * closed on its own, exactly like one compiled from configuration. The
 * checks are shape-only (presence, type, emptiness); the semantics a route
 * pattern or a constraint pattern carries are judged by {@see RoutePath}
 * and {@see RouteConstraints}.
 *
 * Absent optional fields default to their empty form (an empty list, an
 * empty map, a null name); a *present* but empty or wrongly typed field is
 * always a violation, because "explicitly empty" and "not given" mean
 * different things to the route table.
 *
 * @internal
 */
final class RouteEntryShape
{
    private const array KNOWN_FIELDS = [
        'path' => true,
        'host' => true,
        'version' => true,
        'produces' => true,
        'method' => true,
        'handler' => true,
        'name' => true,
        'middleware' => true,
        'constraints' => true,
        'meta' => true,
        'deprecation' => true,
    ];

    /**
     * Returns the entry's fields, shape-checked and keyed by name.
     *
     * @return array{
     *     path: non-empty-string,
     *     host: ?non-empty-string,
     *     version: ?non-empty-string,
     *     produces: list<string>,
     *     method: list<string>,
     *     handler: non-empty-string,
     *     name: ?non-empty-string,
     *     middleware: list<string>,
     *     constraints: array<string, non-empty-string>,
     *     meta: array<array-key, mixed>,
     *     deprecation: null|array{date: non-empty-string, sunset?: non-empty-string, link?: non-empty-string},
     * }
     */
    public static function fields(mixed $route): array
    {
        if (!\is_array($route)) {
            throw new ConfigException(
                \sprintf('The route entry must be an array, got %s.', get_debug_type($route)),
            );
        }

        foreach (array_keys($route) as $field) {
            if (!isset(self::KNOWN_FIELDS[$field])) {
                throw new ConfigException(
                    \sprintf('The route entry field "%s" is unknown.', $field),
                );
            }
        }

        return [
            'path' => self::requiredString($route, 'path'),
            'host' => self::optionalString($route, 'host'),
            'version' => self::optionalString($route, 'version'),
            'produces' => RouteEntryCollections::stringList($route, 'produces', false),
            'method' => RouteEntryCollections::stringList($route, 'method', true),
            'handler' => self::requiredString($route, 'handler'),
            'name' => self::optionalString($route, 'name'),
            'middleware' => RouteEntryCollections::stringList($route, 'middleware', false),
            'constraints' => RouteEntryCollections::stringMap($route),
            'meta' => RouteEntryMeta::fromRoute($route),
            'deprecation' => self::optionalDeprecation($route),
        ];
    }

    /**
     * A required field holding one non-empty string.
     *
     * @param array<array-key, mixed> $route
     *
     * @return non-empty-string
     */
    private static function requiredString(array $route, string $field): string
    {
        if (!\array_key_exists($field, $route)) {
            throw new ConfigException(
                \sprintf('The route entry field "%s" is required.', $field),
            );
        }

        return RouteEntryCollections::nonEmptyString($route[$field], $field);
    }

    /**
     * An optional field holding one non-empty string, or null when absent.
     *
     * @param array<array-key, mixed> $route
     *
     * @return ?non-empty-string
     */
    private static function optionalString(array $route, string $field): ?string
    {
        if (!\array_key_exists($field, $route)) {
            return null;
        }

        return RouteEntryCollections::nonEmptyString($route[$field], $field);
    }

    /**
     * An optional field holding one deprecation declaration -- a map whose
     * members are the vocabulary {@see RouteDeprecation} owns (`date`
     * required, `sunset` and `link` optional) and whose values are
     * non-empty strings -- or null when absent. The grammar those members
     * carry is the declaration's own semantics, judged by
     * {@see RouteDeprecation} at field-assembly time; this is the
     * shape-only half.
     *
     * @param array<array-key, mixed> $route
     *
     * @return null|array{date: non-empty-string, sunset?: non-empty-string, link?: non-empty-string}
     */
    private static function optionalDeprecation(array $route): ?array
    {
        if (!\array_key_exists('deprecation', $route)) {
            return null;
        }

        $value = $route['deprecation'];

        if (!\is_array($value) || $value === [] || array_is_list($value)) {
            throw new ConfigException(
                'The route entry field "deprecation" must be a non-empty map of declaration members.',
            );
        }

        foreach (array_keys($value) as $member) {
            if (!\is_string($member) || !isset(RouteDeprecation::members()[$member])) {
                throw new ConfigException(\sprintf(
                    'The route entry field "deprecation" carries the unknown member "%s".',
                    \is_string($member) ? $member : get_debug_type($member),
                ));
            }
        }

        if (!\array_key_exists('date', $value)) {
            throw new ConfigException(
                'The route entry field "deprecation" must declare the "date" member.',
            );
        }

        $declaration = [
            'date' => RouteEntryCollections::nonEmptyString($value['date'], 'deprecation.date'),
        ];

        if (\array_key_exists('sunset', $value)) {
            $declaration['sunset'] = RouteEntryCollections::nonEmptyString(
                $value['sunset'],
                'deprecation.sunset',
            );
        }

        if (\array_key_exists('link', $value)) {
            $declaration['link'] = RouteEntryCollections::nonEmptyString(
                $value['link'],
                'deprecation.link',
            );
        }

        return $declaration;
    }
}
