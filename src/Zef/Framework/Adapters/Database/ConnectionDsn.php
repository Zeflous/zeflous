<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;

/**
 * The DSN spelling fold: the driver-specific connection string the
 * PDO constructor reads -- MySQL's charset suffix, PostgreSQL's
 * client-encoding options clause, SQLite's bare path.
 *
 * @internal
 */
final class ConnectionDsn
{
    /**
     * Spells the DSN over the validated configuration's network fields.
     */
    public static function of(string $driver, ?string $host, ?int $port, string $dbname, ?string $charset): string
    {
        return match ($driver) {
            'mysql' => 'mysql:host=' . self::host($driver, $host) . ';port=' . self::port($driver, $port)
                . ';dbname=' . $dbname . ($charset !== null ? ';charset=' . $charset : ''),
            'pgsql' => 'pgsql:host=' . self::host($driver, $host) . ';port=' . self::port($driver, $port)
                . ';dbname=' . $dbname
                . ($charset !== null ? ";options='--client_encoding=" . $charset . "'" : ''),
            'sqlite' => 'sqlite:' . $dbname,
            default => throw new ConnectionException("Unsupported database driver '{$driver}'."),
        };
    }

    /**
     * The network DSN's host, narrowed from the nullable property the
     * SQLite driver legitimately leaves empty: fromArray guarantees the
     * network drivers their host.
     */
    private static function host(string $driver, ?string $host): string
    {
        if ($host === null) {
            throw new ConnectionException("The {$driver} DSN requires a host.");
        }

        return $host;
    }

    /**
     * The network DSN's port, narrowed the same one-way way.
     */
    private static function port(string $driver, ?int $port): int
    {
        if ($port === null) {
            throw new ConnectionException("The {$driver} DSN requires a port.");
        }

        return $port;
    }
}
