<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;

/**
 * The `database.connections` map's own folds: the strict read of the
 * named connection declarations and the one-name lookup the wiring
 * composes over -- the family's map-of-maps grammar, one place.
 *
 * The connection map is the configuration system's own array-in
 * shape travelling toward {@see Adapters\Database\ConnectionConfig::fromArray()}:
 * every value must be the map a connection's own reading folds
 * consume, and the fold here owns only the map's OWN grammar --
 * non-empty string names, map values -- never the fields inside
 * (those belong to the adapter's reading folds, so a misconfigured
 * `host` surfaces with the adapter's exact message, not a
 * duplicated one here). The division is the declaration's own
 * ({@see DatabaseDeclaration} reads names, the adapter reads
 * fields), the same one-command-per-question doctrine every family
 * census in the tree carries.
 *
 * The one-name lookup refuses with the asking knob and the known
 * connection list spelled: a `database.default` naming a connection
 * the host never declared is the deployment defect it is, and the
 * worst failure shape available is the silent one -- an operator
 * believing the wired default composes while the resolution keeps
 * refusing.
 */
final class DatabaseConnections
{
    /**
     * Reads the connections map strictly: absent answers the empty
     * map (the silent posture -- no connection declared), and a map
     * of non-empty-string names to knob maps answers itself, every
     * connection map's own keys narrowed to the word keys a
     * connection's knobs spell.
     *
     * @param mixed $connections the `database.connections` value as the
     *                           frozen repository carries it
     *
     * @return array<string, array<string, mixed>> the named connection
     *                                             declarations, name to config map
     *
     * @throws ConfigException when the value is neither a map nor null,
     *                         or carries a non-string or empty name, or a
     *                         value that is not a map or carries a
     *                         non-word key
     */
    public static function read(mixed $connections): array
    {
        if ($connections === null) {
            return [];
        }

        if (!\is_array($connections)) {
            throw new ConfigException(
                'The database.connections configuration must be a map, '
                . get_debug_type($connections) . ' given.',
            );
        }

        $declared = [];

        foreach ($connections as $name => $connection) {
            if (!\is_string($name) || $name === '') {
                throw new ConfigException(
                    'The database.connections names must be non-empty strings, '
                    . (\is_string($name) ? 'an empty string' : get_debug_type($name)) . ' given.',
                );
            }

            $declared[$name] = DatabaseConnectionKnobs::map($connection, $name);
        }

        return $declared;
    }

    /**
     * Answers one named connection's declaration map, refusing with
     * the asking knob and the known connection list spelled when the
     * name the knob carries was never declared.
     *
     * @param array<string, array<string, mixed>> $connections the declared connections
     * @param string                              $name        the connection name a knob spelled
     * @param string                              $knob        the knob that spelled it
     *                                                         ('default' or 'migrations')
     *
     * @return array<string, mixed> the named connection's declaration map
     *
     * @throws ConfigException when the name carries no declared connection
     */
    public static function one(array $connections, string $name, string $knob): array
    {
        if (isset($connections[$name])) {
            return $connections[$name];
        }

        $known = $connections === [] ? 'none declared' : implode(', ', array_keys($connections));

        throw new ConfigException(
            "The database.{$knob} connection '{$name}' is not declared"
            . " (database.connections: {$known}).",
        );
    }
}
