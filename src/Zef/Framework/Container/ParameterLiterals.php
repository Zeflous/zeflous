<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The parameters map's literal walk: the nested, string-keyed
 * declaration tree flattened into the dot-addressed value map (see
 * {@see ParameterMap} for the knob's own parse).
 *
 * A node is an addressable map when it is non-empty and every key is a
 * string -- such a node recurses, each of its keys joining the address
 * (`['db' => ['host' => ...]]` carries `db.host`). Everything else is
 * a value: a scalar, null, or an array -- a list (`['a', 'b']`, any
 * integer key present) is the parameter's whole value, never a set of
 * positions to address, so `#[Value('db.replicas')]` answers the
 * complete list and there is no `db.replicas.0` spelling at all. An
 * empty array is likewise a value (the empty-list parameter), not a
 * node that would silently vanish from the walk.
 *
 * Every value is checked injectable through the family's shared
 * predicate ({@see ParameterInjectability}) -- an object or resource
 * under an address (at any depth of its array) is refused with the
 * address named, at parse, rather than surfacing as a surprise at the
 * first resolution.
 */
final readonly class ParameterLiterals
{
    /**
     * Flattens the literal half of the knob into the dot-addressed map.
     *
     * @param array<array-key, mixed> $source the knob's map, the reserved
     *                                        'providers' key already lifted out
     *
     * @return array<string, mixed> address => value
     */
    public static function values(array $source): array
    {
        $flat = [];

        foreach (array_keys($source) as $segment) {
            $flat = [...$flat, ...self::walk((string) $segment, $source[$segment])];
        }

        return $flat;
    }

    /**
     * One address's walk: a string-keyed, non-empty array node recurses;
     * anything else is the value, checked injectable where it carries
     * elements.
     *
     * @return array<string, mixed> the addresses this node contributes
     */
    private static function walk(string $address, mixed $value): array
    {
        if (\is_array($value) && $value !== [] && self::isAddressable($value)) {
            $flat = [];

            foreach (array_keys($value) as $segment) {
                $flat = [...$flat, ...self::walk($address . '.' . $segment, $value[$segment])];
            }

            return $flat;
        }

        $offender = ParameterInjectability::offender($value);

        if ($offender !== null) {
            throw ParameterRefusals::leafNotInjectable($address, $offender);
        }

        return [$address => $value];
    }

    /**
     * Whether the node's keys are all strings (the addressable shape).
     *
     * @param array<array-key, mixed> $node
     */
    private static function isAddressable(array $node): bool
    {
        return array_all(array_keys($node), static fn (int|string $key): bool => \is_string($key));
    }
}
