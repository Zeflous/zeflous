<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;

/**
 * The network port's strict reading: an int or numeric string in the
 * 1-65535 range, with the driver's default when absent.
 *
 * @internal
 */
final class ConnectionPort
{
    private const int DEFAULT_MYSQL_PORT = 3306;

    private const int DEFAULT_PGSQL_PORT = 5432;

    /**
     * @param array<string, mixed> $config
     */
    public static function read(array $config, string $driver): int
    {
        /** @var mixed $value the port knob, judged by the reading below */
        $value = $config['port'] ?? null;
        if ($value === null) {
            return $driver === 'mysql' ? self::DEFAULT_MYSQL_PORT : self::DEFAULT_PGSQL_PORT;
        }

        if (\is_int($value)) {
            $port = $value;
        } elseif (\is_string($value) && preg_match('/^\d{1,5}$/', $value) === 1) {
            $port = (int) $value;
        } else {
            throw new ConnectionException('Port must be an int or a numeric string (1-65535).');
        }

        if ($port < 1 || $port > 65535) {
            throw new ConnectionException("Port {$port} is out of range (1-65535).");
        }

        return $port;
    }
}
