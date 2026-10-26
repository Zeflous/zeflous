<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The payload's JSON-round-trip walk: the one fold that answers
 * whether a validated payload map can survive its serialized
 * spelling. The shape's own five kinds admit values the JSON
 * grammar cannot carry -- a list field holds whatever the producer
 * put in it, and PHP strings are byte bags that may not be UTF-8
 * -- so the durable boundary must refuse those values by name
 * instead of letting a later encode or decode discover them as
 * corruption.
 *
 * The walk refuses, naming the path and the kind it found: an
 * object or resource inside a list, a non-finite float (NAN and
 * both infinities), a string that is not valid UTF-8, and a
 * non-string key at the payload's root. Nested arrays are walked
 * to any depth, their positions and keys spelled into the path --
 * `tags[1]`, `user[name]` -- so the producer reads exactly which
 * value to change. Nested integer keys are legal (PHP itself
 * casts a numeric string key to an integer, and a list round-trips
 * as a list); only the root must stay a map, because the field
 * names are the record's own vocabulary.
 */
final class SerializableValues
{
    /**
     * Answers the first value that cannot survive the round trip,
     * or null when the whole map can. The answer is a phrase the
     * refusing boundary embeds: the kind and the path, e.g.
     * `stdClass at tags[1]`, `NAN at ratio`, `a string that is not
     * valid UTF-8 at memo`.
     *
     * @param array<array-key, mixed> $values the payload map the walk judges
     *
     * @return null|string the offense phrase, null when every value survives
     */
    public static function offense(array $values): ?string
    {
        /** @var mixed $value one field's value, judged by the walk below */
        foreach ($values as $key => $value) {
            if (!\is_string($key)) {
                return 'a non-string key ' . var_export($key, true)
                    . ' at the payload root';
            }

            $offense = self::valueOffense($value, $key);

            if ($offense !== null) {
                return $offense;
            }
        }

        return null;
    }

    /**
     * @param mixed $value one field's value
     */
    private static function valueOffense(mixed $value, string $path): ?string
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return null;
        }

        if (\is_float($value)) {
            if (is_finite($value)) {
                return null;
            }

            if (is_nan($value)) {
                return 'NAN at ' . $path;
            }

            return ($value === \INF ? 'INF' : '-INF') . ' at ' . $path;
        }

        if (\is_string($value)) {
            return preg_match('//u', $value) === 1
                ? null
                : 'a string that is not valid UTF-8 at ' . $path;
        }

        if (\is_array($value)) {
            return self::arrayOffense($value, $path);
        }

        return get_debug_type($value) . ' at ' . $path;
    }

    /**
     * @param array<array-key, mixed> $value the list-kind value being walked
     */
    private static function arrayOffense(array $value, string $path): ?string
    {
        /** @var mixed $item one nested value, judged by the walk below */
        foreach ($value as $key => $item) {
            $offense = self::valueOffense($item, $path . '[' . $key . ']');

            if ($offense !== null) {
                return $offense;
            }
        }

        return null;
    }
}
