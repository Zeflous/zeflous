<?php

declare(strict_types=1);

namespace Zef\Framework;

use Psr\Log\LoggerInterface;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Database\PdoQueryLogArming;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\Migrator;
use Zef\Framework\Database\TableGateway;
use Zef\Framework\Database\TransactionManager;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Middleware\ErrorStorageWiring;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The database family's wiring onto the configuration system: the
 * composition fold that answers the family's entry points from the
 * frozen repository -- the `database` knob block's first live
 * reader, the census-moment every opt-in family answered (rule 17's
 * config integration lands with the first live reader, never
 * before).
 *
 * The folds answer the four service identities the framework
 * profile's own {@see FrameworkDefaults} registration names: the
 * default connection (`database.connection`, the {@see PdoConnection}
 * over {@see ConnectionConfig::fromArray()} with the
 * unbounded-statement audit emission's two optional seams -- the
 * shared meter the registration hands in, and the WARN record over
 * the deployment's ONE structured log address the slow-request
 * family's own doctrine spells, so the audit records join the error
 * and slow-request records in the one sink -- and with the
 * per-query logging family's own bracket when the declaration arms
 * it: the `database.query_log` block's policy over the same shared
 * meter and the same one log address, so the query records land in
 * the one sink the audit and slow-request records land in), the
 * migration runner (`database.migrator`, over the per-purpose
 * connection: the `database.migrations` name the host spelled, else
 * the default -- a deployment may run its migrations on a connection
 * of their own naming without ever spelling the default), the
 * managed-scope transaction manager (`database.transactions`, over
 * the shared default connection the registration hands in), and the
 * generic per-table gateway ({@see repository()}, over the connection
 * the host's own per-table service declaration hands in).
 *
 * The silent declaration (the empty `database.default` knob, or the
 * absent `database` block entirely) is a refusal with the way out
 * spelled, never a null answer: a host resolving the wired
 * default's service identity is asking for the framework's
 * connection, and the honest answer to an undeclared ask is the
 * knob's own grammar -- spell the connection (database.connections
 * plus database.default), or compose the family by hand over the
 * port the way every composition before this fold did. The
 * {@see EventStorageWiring::store()} refusal spelled the same
 * posture first, and for the same reason: a directory -- or a
 * socket -- the host never asked for is never silently defaulted.
 *
 * The registrations' lifetimes carry the posture: the connection
 * and the migrator defer behind native lazy proxies (the `lazy`
 * key), so the singleton warmer's boot-time walk answers the proxy
 * without running the factory -- the silent refusal fires only at
 * the host's own first use, never at the boot, the same
 * never-at-the-boot discipline the events family's transient
 * registration planted -- while the shared connection's one
 * materialised instance still answers every later ask (a singleton
 * materialises once, through the one proxy it keeps), which a
 * transient could never promise a connection.
 *
 * @api
 */
final readonly class DatabaseWiring
{
    /**
     * Answers the wired default connection from the frozen
     * repository: the `database.default` name's declared connection
     * map, composed over the adapter's own reading folds with the
     * audit emission's two optional seams.
     *
     * @param null|MeterInterface $meter the unbounded-statement audit
     *                                   counter seam, or null when the caller wires no meter
     *
     * @throws ConfigException when the declaration is silent (no
     *                         default connection spelled), the named connection is not
     *                         declared, or any connection field the adapter's reading
     *                         folds consume refuses
     */
    public static function connection(Config $config, ?MeterInterface $meter = null): PdoConnection
    {
        $databaseDeclaration = DatabaseDeclaration::fromConfig($config);

        if ($databaseDeclaration->wantsWiredConnection()) {
            return self::pdoConnection(
                $config,
                $databaseDeclaration,
                $databaseDeclaration->default,
                'default',
                $meter,
            );
        }

        throw new ConfigException(
            'The database.connection wired default is not declared:'
            . ' spell a connection (database.connections plus database.default)'
            . ' or compose the family over your own ConnectionInterface.',
        );
    }

    /**
     * Answers the wired migration runner from the frozen repository:
     * the migration runner over the per-purpose connection -- the
     * `database.migrations` name the host spelled, else the default
     * connection's name -- with the runner's own declaration
     * defaults (the wall-clock provider and the lock corridor the
     * orchestration slice pinned).
     *
     * @param null|MeterInterface $meter the unbounded-statement audit
     *                                   counter seam, or null when the caller wires no meter
     *
     * @throws ConfigException when neither a migrations purpose nor a
     *                         default connection is spelled, the effective name's
     *                         connection is not declared, or any connection field the
     *                         adapter's reading folds consume refuses
     */
    public static function migrator(Config $config, ?MeterInterface $meter = null): Migrator
    {
        $databaseDeclaration = DatabaseDeclaration::fromConfig($config);
        $migrationsName = $databaseDeclaration->migrationsName();

        if ($migrationsName === '') {
            throw new ConfigException(
                'The database.migrator wired default is not declared:'
                . " spell the migration runner's connection (database.migrations"
                . ' or database.default over database.connections) or compose'
                . ' the runner over your own ConnectionInterface.',
            );
        }

        return new Migrator(
            self::pdoConnection(
                $config,
                $databaseDeclaration,
                $migrationsName,
                $databaseDeclaration->migrations !== '' ? 'migrations' : 'default',
                $meter,
            ),
        );
    }

    /**
     * Answers the managed-scope transaction manager over the shared
     * connection the registration hands in -- the connection the
     * `database.connection` identity answers, so the manager's
     * scopes and the host's own queries ride the one connection the
     * default composes.
     *
     * The slow-hook guard stays at its default posture (unset): the
     * family's knob block deliberately carries no threshold of its
     * own, because a threshold no deployment asked for is a log
     * stream no operator reads -- the same opt-in discipline the
     * guard's own seam spells.
     */
    public static function transactions(ConnectionInterface $connection): TransactionManager
    {
        return new TransactionManager($connection);
    }

    /**
     * Answers the generic per-table gateway over the connection the
     * host's own per-table service declaration hands in: the
     * constructible spelling of the family's gateway base, the
     * table name validated eagerly so a typo surfaces at
     * construction, not at the first query.
     *
     * @param string $table the gateway's table name, the base's own
     *                      eager grammar the constructor runs
     */
    public static function repository(ConnectionInterface $connection, string $table): TableGateway
    {
        return new TableGateway($connection, $table);
    }

    /**
     * Composes one named wired connection: the declaration map the
     * name resolves to, read through the adapter's own folds, with
     * the audit emission's counter seam and its WARN record over
     * the deployment's ONE structured log address (an address the
     * configuration answers -- the slow-request family's own
     * derivation -- or the silent posture's null seam), and with the
     * query-log family's own bracket when the declaration arms it
     * (over the same two shared sinks, so every record the family
     * writes lands in the one sink).
     *
     * @param Config              $config              the frozen repository
     * @param DatabaseDeclaration $databaseDeclaration the family's declaration
     * @param string              $name                the connection name the ask spelled
     * @param string              $knob                the knob that spelled it
     * @param null|MeterInterface $meter               the audit counter seam
     *
     * @throws ConfigException when the name carries no declared
     *                         connection, or any connection field the adapter's
     *                         reading folds consume refuses
     */
    private static function pdoConnection(
        Config $config,
        DatabaseDeclaration $databaseDeclaration,
        string $name,
        string $knob,
        ?MeterInterface $meter,
    ): PdoConnection {
        $logger = self::auditLogger($config);

        return new PdoConnection(
            ConnectionConfig::fromArray(DatabaseConnections::one($databaseDeclaration->connections, $name, $knob)),
            null,
            $meter,
            $logger,
            PdoQueryLogArming::bracket($databaseDeclaration->queryLogPolicy, $meter, $logger),
        );
    }

    /**
     * The audit emission's record seam: the JSON line logger over
     * the deployment's one structured log address, or null when the
     * configuration answers none (no explicit path and no declared
     * storage root) -- the slow-request family's own logger fold,
     * reached one-way so the audit records and the slow-request
     * records land in the one sink.
     *
     * @return null|LoggerInterface the sink the unbounded-statement
     *                              WARN records travel through
     */
    private static function auditLogger(Config $config): ?LoggerInterface
    {
        $logAddress = ErrorStorageWiring::logAddress($config);

        return $logAddress === '' ? null : new JsonLineLogger($logAddress);
    }
}
