<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;

/**
 * The scalar connection fields' strict readings: the driver word, the
 * database name (with SQLite's printable-path rule) and the optional
 * string knobs -- each fold reads its own key from the configuration
 * map and refuses its own misconfiguration with the field named, so
 * {@see ConnectionConfig::fromArray()} stays a thin composition.
 *
 * The driver vocabulary and the drivers' default ports live here: the
 * readings that consume them are their one consumer.
 *
 * @internal
 */
final class ConnectionFields
{
    public const array DRIVERS = ['mysql', 'pgsql', 'sqlite'];

    /**
     * The driver word: one of the supported drivers, any other value
     * refused with the allowed list spelled.
     *
     * @param array<string, mixed> $config
     */
    public static function readDriver(array $config): string
    {
        /** @var mixed $value the driver word, judged by the reading below */
        $value = $config['driver'] ?? null;
        if (\is_string($value) && \in_array($value, self::DRIVERS, true)) {
            return $value;
        }

        throw new ConnectionException(
            "Unknown database driver '" . (\is_scalar($value) ? (string) $value : get_debug_type($value))
            . "' (allowed: " . implode(', ', self::DRIVERS) . ').',
        );
    }

    /**
     * The database name: non-empty, and for SQLite either the in-memory
     * word or a printable-ASCII path.
     *
     * @param array<string, mixed> $config
     */
    public static function readDbname(array $config, string $driver): string
    {
        /** @var mixed $value the database name, judged by the reading below */
        $value = $config['dbname'] ?? null;
        if (\is_string($value) && $value !== '') {
            if ($driver === 'sqlite' && $value !== ':memory:' && preg_match('/^[\x20-\x7E]{1,4096}$/', $value) !== 1) {
                throw new ConnectionException(
                    "SQLite database path must be ':memory:' or printable ASCII (got non-printable input).",
                );
            }

            return $value;
        }

        throw new ConnectionException("Database name (dbname) must be a non-empty string for driver '{$driver}'.");
    }

    /**
     * An optional string knob: the value is a string or the absent
     * null -- anything else halts with the field named.
     *
     * @param array<string, mixed> $config
     */
    public static function readOptionalString(array $config, string $key, string $field): ?string
    {
        /** @var mixed $value the optional string knob, judged by the reading below */
        $value = $config[$key] ?? null;
        if ($value === null || \is_string($value)) {
            return $value;
        }

        throw new ConnectionException("{$field} must be a string or null.");
    }
}
