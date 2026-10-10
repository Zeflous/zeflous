<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

/**
 * Copy-on-write deep-path remover for the configuration layer.
 *
 * Removes the leaf addressed by a parsed segment path and returns a *new*
 * array, sharing untouched subtrees (PHP copy-on-write) so immutable
 * `Config::withUnset()` stays cheap. The original array is never modified.
 */
final readonly class DotRemover
{
    /**
     * Removes the leaf at the segment path.
     *
     * An absent path leaves the data unchanged: removing a key that is not
     * there is a no-op, and a scalar intermediate blocks the descent (the
     * deeper path cannot exist). The caller still receives a fresh array.
     *
     * @param array<array-key, mixed> $data
     * @param non-empty-list<string>  $segments
     *
     * @return array<array-key, mixed>
     */
    public static function remove(array $data, array $segments): array
    {
        $head = $segments[0];
        $rest = \array_slice($segments, 1);

        if ($rest === []) {
            unset($data[$head]);
        } elseif (\array_key_exists($head, $data) && \is_array($data[$head])) {
            $data[$head] = self::remove($data[$head], $rest);
        }

        return $data;
    }
}
