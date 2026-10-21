<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Assembles one route entry's validated field map -- the engine's
 * consumption view of the Standard Route Schema: the declared host bound
 * to the path ({@see RouteHostBinding}), the canonical version, the
 * normalised produces list and the upper-cased, sorted, de-duplicated
 * method list, with the passthrough fields kept verbatim.
 *
 * @internal
 */
final class RouteFields
{
    /**
     * @param array{
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
     * } $entry the shape-checked route entry
     * @param null|non-empty-string $host the canonicalised declared host
     *
     * @return array{
     *     host: ?non-empty-string,
     *     version: ?non-empty-string,
     *     produces: list<string>,
     *     method: list<string>,
     *     handler: non-empty-string,
     *     name: ?non-empty-string,
     *     middleware: list<string>,
     *     meta: array<array-key, mixed>,
     * }
     */
    public static function fromEntry(array $entry, ?string $host): array
    {
        return [
            'host' => $host,
            'version' => $entry['version'] === null ? null : RouteVersion::canonical($entry['version']),
            'produces' => RouteProduces::normalize($entry['produces']),
            'method' => self::normalizedMethods($entry['method']),
            'handler' => $entry['handler'],
            'name' => $entry['name'],
            'middleware' => $entry['middleware'],
            'meta' => $entry['meta'],
        ];
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
