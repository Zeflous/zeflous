<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The database family's named-connection wiring: the per-purpose
 * composition a family's own knob spells, split out of {@see
 * DatabaseWiring} the way the difficulty ceiling splits every
 * composition that outgrows its file -- the family's wiring fold
 * answers the wired default's ONE connection, this fold answers
 * the NAMED one (the connection a family's own knob spells, the
 * migration runner's per-purpose name doctrine carried to any
 * family that asks for a connection of its own naming).
 *
 * The empty name folds to the default connection exactly as
 * {@see DatabaseDeclaration::migrationsName()} folds the migration
 * runner's own, so a deployment may feed a family's store on a
 * connection of their own naming without ever spelling the
 * default. The asking knob names the ask, so the one-name
 * lookup's refusal spells which family's word named a connection
 * the `database.connections` map does not carry -- and the silent
 * family (no name spelled, no default declared) is the named halt
 * the family's own composition reads, never a silent null.
 */
final readonly class DatabaseConnectionWiring
{
    /**
     * Answers one NAMED wired connection: the name a family's own
     * knob spelled, the default connection when the knob stayed
     * silent, composed over the family wiring's own composition
     * seam with the audit emission's and the query log's silent
     * seams (the wired default's own composition carries them; a
     * family's named ask composes the plain connection).
     *
     * @param Config $config     the frozen repository
     * @param string $name       the connection name the asking family's knob
     *                           spelled, '' the default connection
     * @param string $askingKnob the knob that spelled the name, for the
     *                           refusal the one-name lookup answers
     *
     * @throws ConfigException when no connection is spelled at all
     *                         (neither the name nor a default), the effective name's
     *                         connection is not declared, or any connection field the
     *                         adapter's reading folds consume refuses
     */
    public static function connection(Config $config, string $name, string $askingKnob): PdoConnection
    {
        $databaseDeclaration = DatabaseDeclaration::fromConfig($config);
        $effectiveName = $name === '' ? $databaseDeclaration->default : $name;

        if ($effectiveName === '') {
            throw new ConfigException(
                "The {$askingKnob} wired default is not declared: spell a"
                . ' connection (database.connections plus database.default)'
                . ' or compose the family over your own ConnectionInterface.',
            );
        }

        return DatabaseWiring::pdoConnection(
            $config,
            $databaseDeclaration,
            $effectiveName,
            $askingKnob,
            null,
        );
    }
}
