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
     * One value's own answer -- the array arm recurses, and a
     * leaf answers the survival question first: the kinds JSON
     * carries answer null, every other kind answers its own
     * phrase with the path. Three returns, one per honest
     * answer: the recursion, the survival pass, and the phrase.
     *
     * @param mixed $value one field's value
     */
    private static function valueOffense(mixed $value, string $path): ?string
    {
        if (\is_array($value)) {
            return self::arrayOffense($value, $path);
        }

        if (self::survives($value)) {
            return null;
        }

        return self::leafKind($value) . ' at ' . $path;
    }

    /**
     * The survival question -- whether the value's own kind can
     * cross the JSON grammar: null, booleans, integers and
     * finite floats cross untouched, and a string crosses only
     * when it is valid UTF-8.
     *
     * @param mixed $value one leaf value
     */
    private static function survives(mixed $value): bool
    {
        if (\is_string($value)) {
            return preg_match('//u', $value) === 1;
        }

        if (\is_float($value)) {
            return is_finite($value);
        }

        return \in_array(get_debug_type($value), ['null', 'bool', 'int'], true);
    }

    /**
     * The refusing phrase's own kind -- what the boundary embeds
     * before the path: a string that is not valid UTF-8, NAN and
     * both infinities, or the engine's own name for any other
     * kind (an object or resource inside a list).
     *
     * @param mixed $value one leaf value the survival question refused
     */
    private static function leafKind(mixed $value): string
    {
        if (\is_string($value)) {
            return 'a string that is not valid UTF-8';
        }

        if (\is_float($value)) {
            return self::nonFiniteSpelling($value);
        }

        return get_debug_type($value);
    }

    /**
     * The non-finite float's own spelling -- NAN first, then both
     * infinities by sign: three phrases, one per arithmetic misfit
     * the JSON grammar cannot carry, each spelled as its own
     * statement so the kind the boundary names is the kind the
     * producer reads.
     */
    private static function nonFiniteSpelling(float $value): string
    {
        if (is_nan($value)) {
            return 'NAN';
        }

        return $value === \INF ? 'INF' : '-INF';
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
