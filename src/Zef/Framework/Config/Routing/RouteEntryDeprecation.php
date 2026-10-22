<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The deprecation side of the raw-entry shape check: one question -- is
 * this route entry's `deprecation` field a declaration map the engine can
 * compile, and what are its members?
 *
 * Mirrors {@see RouteEntryMeta::fromRoute()}: the check is shape-only
 * (the field is a non-empty map, its members come from
 * {@see RouteDeprecation::members()}'s vocabulary, `date` is present, and
 * every value is a non-empty string); the grammar those members carry is
 * the declaration's own semantics, judged by {@see RouteDeprecation} at
 * field-assembly time. An absent field answers null; a present-but-empty
 * or wrongly-typed field is a violation, because "explicitly empty" and
 * "not given" mean different things to the route table.
 *
 * @internal
 */
final class RouteEntryDeprecation
{
    /**
     * The entry's shape-checked deprecation declaration, or null when the
     * entry carries none.
     *
     * @param array<array-key, mixed> $route the raw route entry
     *
     * @return null|array{date: non-empty-string, sunset?: non-empty-string, link?: non-empty-string}
     *
     * @throws ConfigException when the field is present but not a declaration map
     */
    public static function fromRoute(array $route): ?array
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
