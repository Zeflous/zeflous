<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The database family's declaration fold: the strict read of the one
 * `database` knob block the wiring composes over -- the default
 * connection's name, the named connection maps, and the migration
 * runner's per-purpose name -- answered as one immutable value
 * object {@see DatabaseWiring} composes.
 *
 * The family census (which children the `database` map may carry)
 * is the family's own fold ({@see DatabaseKnobs}), the connections
 * map's grammar is its own ({@see DatabaseConnections}), and the
 * two name knobs read through the declaration's own string seam --
 * each reached one-way exactly as the events family's declaration
 * reaches its own, so this fold owns only the family's grammar:
 * every knob read strictly, the unknown knob and the foreign type
 * each a named halt with the known list spelled ({@see
 * EventDeliveryDeclaration} and {@see Http\StrictKnobs} lineages).
 *
 * The silent family is an ANSWER, not a failure: an absent
 * `database` block, or an absent `default` knob, declares no wired
 * default -- the connection stays opt-in exactly as the events
 * family's wired store does, because the framework ships no
 * database of its own and a host's declaration is the only honest
 * way one arrives. It is the wiring's own question (never this
 * fold's) what resolving the wired default's service identity
 * without declaring one should answer.
 *
 * The per-purpose name (`migrations`) reads with the same string
 * seam and folds to the migration runner's EFFECTIVE connection
 * name -- its own name when the host spelled one, the default
 * connection's name when the knob stayed silent, so a deployment
 * may run its migrations on a connection of their own naming
 * without ever spelling the default.
 */
final readonly class DatabaseDeclaration
{
    /**
     * @param array<string, array<string, mixed>> $connections the named
     *                                                         connection declarations, name to config map
     * @param string                              $migrations  the migration runner's per-purpose
     *                                                         connection name ('' the default connection)
     */
    public function __construct(
        public string $default,
        public array $connections,
        public string $migrations,
    ) {
    }

    /**
     * Reads the declaration from the frozen repository's `database`
     * subtree: the census guards the map, the connections fold reads
     * the map-of-maps, and the two name knobs read through the
     * declaration's own string seam.
     *
     * @throws ConfigException when the map is malformed or carries an
     *                         unknown knob, the connections map is likewise malformed, or
     *                         either name knob is not a string
     */
    public static function fromConfig(Config $config): self
    {
        return self::fromMap($config->get('database'));
    }

    /**
     * Whether the declaration asks for the wired default at all: the
     * non-empty default name. The wiring reads this to decide
     * between composing the default connection and refusing with the
     * way out spelled -- a question the declaration itself never
     * owns.
     */
    public function wantsWiredConnection(): bool
    {
        return $this->default !== '';
    }

    /**
     * The migration runner's effective connection name: the
     * per-purpose name the host spelled, else the default
     * connection's name -- the one-way fallback the per-purpose
     * doctrine spells, so a named migrations connection never
     * requires the default to be declared too.
     */
    public function migrationsName(): string
    {
        return $this->migrations !== '' ? $this->migrations : $this->default;
    }

    /**
     * Reads the declaration from the `database` family's own map.
     *
     * @param mixed $database the family's map as the frozen repository
     *                        carries it: a knob map, or null when the family is
     *                        absent
     *
     * @throws ConfigException when the map is malformed or carries
     *                         an unknown knob, or any knob's value refuses
     */
    private static function fromMap(mixed $database): self
    {
        $map = DatabaseKnobs::familyMap($database);

        return new self(
            self::readName($map['default'] ?? '', 'default'),
            DatabaseConnections::read($map['connections'] ?? null),
            self::readName($map['migrations'] ?? '', 'migrations'),
        );
    }

    /**
     * A name knob's own strict reading: any connection-name string
     * is the deployment's own word (the empty string the silent
     * posture), and every other type halts with the knob named.
     *
     * @throws ConfigException when the value is not a string
     */
    private static function readName(mixed $name, string $knob): string
    {
        if (!\is_string($name)) {
            throw new ConfigException("database.{$knob} must be a connection name string.");
        }

        return $name;
    }
}
