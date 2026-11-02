<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The `database` map's knob census: the closed set of children the
 * family's own map may carry, refused with the known list spelled
 * out when a map carries anything else -- the lineage the events
 * family's own {@see EventDeliveryKnobs} spelled for the tree's
 * family-additive census doctrine, itself the HTTP lane's
 * {@see Http\StrictKnobs} lineage.
 *
 * A typo'd key must never fall through to the silent posture
 * unnoticed: a `database.conections` misspelling that fell through
 * would leave a deployment believing its connections are declared
 * while the wiring keeps refusing the ask -- the operator believes
 * the knob is live while the silent posture's is, exactly the miss
 * the strict census exists to catch. The census also owns the
 * family map's own normalisation ({@see familyMap()}): the absent
 * block answers the silent posture's empty map, and every other
 * non-map shape halts -- the same guard walk the events family's
 * {@see EventFamilies::subtree()} owns for its family block.
 *
 * The census is family-additive: the per-purpose connection knobs
 * the roadmap's later purposes name (each its own recorded slice)
 * join the known list here, and every boot that reads the family
 * through the declaration finds the list already guarding the map.
 */
final class DatabaseKnobs
{
    /**
     * The children the `database` map owns, in declaration order --
     * the one list the census and the reading share: `default` the
     * default connection's name, `connections` the named connection
     * maps, `migrations` the migration runner's per-purpose name.
     */
    private const array KNOBS = ['default', 'connections', 'migrations'];

    /**
     * The family's own map, guarded: the absent block answers the
     * silent posture's empty map, a map answers itself with every
     * child census-checked, and every other shape halts.
     *
     * @param mixed $database the `database` family's value as the
     *                        frozen repository carries it
     *
     * @return array<array-key, mixed> the family's own knob map
     *
     * @throws ConfigException when the family is neither a map nor
     *                         absent, or carries an unknown knob
     */
    public static function familyMap(mixed $database): array
    {
        if ($database === null) {
            return [];
        }

        if (!\is_array($database)) {
            throw new ConfigException(
                'The database configuration must be a map, ' . get_debug_type($database) . ' given.',
            );
        }

        self::rejectUnknown($database);

        return $database;
    }

    /**
     * Rejects the map's unknown keys with the known list spelled
     * out.
     *
     * @param array<array-key, mixed> $map the `database` family's own map
     *
     * @throws ConfigException when the map carries an unknown knob
     */
    private static function rejectUnknown(array $map): void
    {
        $spelled = implode(', ', self::KNOBS);

        foreach (array_keys($map) as $knob) {
            if (\is_string($knob) && \in_array($knob, self::KNOBS, true)) {
                continue;
            }

            throw new ConfigException(
                "The database configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: {$spelled}).",
            );
        }
    }
}
