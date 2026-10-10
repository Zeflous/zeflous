<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

/**
 * Dot-notation key mechanics shared by the configuration layer.
 *
 * A configuration key is a `.`-separated path whose segments address nested
 * arrays; numeric segments address list offsets ("users.0.name"). This class
 * is the single place that parses and traverses those paths, so the {@see Config}
 * repository stays free of traversal mechanics.
 */
final readonly class DotKey
{
    /**
     * Splits a dot-notation key into its segments.
     *
     * A key must have at least one segment and no empty segment, so "", ".",
     * ".a", "a." and "a..b" are all malformed and rejected. Whitespace inside
     * a segment is part of the segment, not a separator.
     *
     * @return non-empty-list<string>
     */
    public static function parse(string $key): array
    {
        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new ConfigException(
                    \sprintf('Configuration key "%s" is malformed: empty segment.', $key),
                );
            }
        }

        return $segments;
    }

    /**
     * Resolves the segments against the data and reports whether the full
     * path exists, together with the value it addresses.
     *
     * A missing key, a missing intermediate segment or an intermediate
     * segment that is not an array all resolve to `[false, null]`; a `null`
     * value stored at the full path resolves to `[true, null]`, which is how
     * "explicitly null" stays distinguishable from "absent".
     *
     * @param array<array-key, mixed> $data
     * @param list<string>            $segments
     *
     * @return array{0: bool, 1: mixed}
     */
    public static function lookup(array $data, array $segments): array
    {
        return self::lookupValue($data, $segments);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $segments
     *
     * @return array{0: bool, 1: mixed}
     */
    private static function lookupValue(array $data, array $segments): array
    {
        if ($segments === []) {
            return [true, $data];
        }

        if (!\array_key_exists($segments[0], $data)) {
            return [false, null];
        }

        return self::lookupNext($data[$segments[0]], \array_slice($segments, 1));
    }

    /**
     * @param list<string> $segments
     *
     * @return array{0: bool, 1: mixed}
     */
    private static function lookupNext(mixed $value, array $segments): array
    {
        if ($segments === []) {
            return [true, $value];
        }

        if (!\is_array($value)) {
            return [false, null];
        }

        return self::lookupValue($value, $segments);
    }
}
