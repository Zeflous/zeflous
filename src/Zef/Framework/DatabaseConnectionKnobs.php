<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * One connection's own knob map: the word keys a connection's knobs
 * spell, narrowed from the mixed keys the frozen repository carries
 * so the adapter's reading folds ({@see Adapters\Database\ConnectionConfig::fromArray()})
 * receive the map their own grammar already assumes.
 *
 * The fold exists for the same census reason every family's reading
 * folds do ({@see DatabaseKnobs} the family's own census,
 * {@see DatabaseConnections} the map-of-maps): a numbered key inside
 * a connection declaration is a knob no reader would ever find --
 * the adapter's folds read named keys only -- and the worst failure
 * shape available is the silent one, an operator believing a
 * misspelled or malformed knob is live while every reader walks
 * past it. The halts name the connection whose map refused.
 */
final class DatabaseConnectionKnobs
{
    /**
     * Reads one connection's own knob map strictly: a map of word
     * keys answers itself, every other shape halts with the
     * connection named.
     *
     * @param mixed $connection the connection's declaration as the
     *                          `database.connections` map carries it
     *
     * @return array<string, mixed> the connection's own knob map
     *
     * @throws ConfigException when the declaration is not a map or
     *                         carries a non-word key
     */
    public static function map(mixed $connection, string $name): array
    {
        if (!\is_array($connection)) {
            throw new ConfigException(
                "The database.connections.{$name} declaration must be a map, "
                . get_debug_type($connection) . ' given.',
            );
        }

        $knobs = [];

        foreach ($connection as $key => $value) {
            if (!\is_string($key)) {
                throw new ConfigException(
                    "The database.connections.{$name} knobs must be word keys, "
                    . get_debug_type($key) . ' given.',
                );
            }

            $knobs[$key] = $value;
        }

        return $knobs;
    }
}
