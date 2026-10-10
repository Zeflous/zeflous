<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

/**
 * Copy-on-write deep-path writer for the configuration layer.
 *
 * The write-side counterpart of {@see DotKey}: writing a value at a parsed
 * segment path returns a *new* array, sharing untouched subtrees (PHP
 * copy-on-write) so immutable `Config::with*` mutations stay cheap. The
 * original array is never modified.
 */
final readonly class DotWriter
{
    /**
     * Writes the value at the segment path.
     *
     * Missing intermediate arrays are created on the way down; a non-array
     * intermediate (a scalar or null) is replaced by a fresh array, because a
     * path that is being written to owns its shape. Numeric segments write at
     * the integer offset -- a missing offset is created as-is, without
     * re-indexing the surrounding list.
     *
     * @param array<array-key, mixed> $data
     * @param non-empty-list<string>  $segments
     *
     * @return array<array-key, mixed>
     */
    public static function set(array $data, array $segments, mixed $value): array
    {
        $head = $segments[0];
        $rest = \array_slice($segments, 1);

        if ($rest === []) {
            return array_replace($data, [$head => $value]);
        }

        return array_replace(
            $data,
            [$head => self::set(self::nestable($data[$head] ?? null), $rest, $value)],
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function nestable(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }
}
