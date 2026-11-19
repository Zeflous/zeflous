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
     * word or a printable-ASCII path; for the network drivers a
     * printable-ASCII word without the DSN's own separator -- the name
     * is interpolated into the DSN string verbatim ({@see
     * ConnectionDsn}), so a `;` would split it and a non-printable
     * byte would reach the driver's own parser (issue #787's finding
     * 4: the one knob the strict doctrine reads must not be the one
     * field that flows to the driver unchecked).
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

            if ($driver !== 'sqlite' && preg_match('/^[\x21-\x3A\x3C-\x7E]{1,64}$/', $value) !== 1) {
                throw new ConnectionException(
                    "Database name (dbname) for driver '{$driver}' must be printable ASCII without"
                    . " the ';' separator (1-64 characters).",
                );
            }

            return $value;
        }

        throw new ConnectionException("Database name (dbname) must be a non-empty string for driver '{$driver}'.");
    }

    /**
     * The charset knob, strictly read (issue #787's finding 4): the
     * word flows into the DSN -- MySQL's `charset=` suffix, PostgreSQL's
     * `options='--client_encoding=...'` clause -- so a free string
     * would flow verbatim into the driver's own option parsing (a
     * `';` or a `'` breaking the clauses apart). The grammar is the
     * one every engine's encoding word spells: letters, digits,
     * underscore, dash and dot, one to sixty-four characters --
     * `utf8`, `utf8mb4`, `UTF-8`, `latin1`, `UNICODE` all carry.
     *
     * @param array<string, mixed> $config
     */
    public static function readCharset(array $config): ?string
    {
        /** @var mixed $value the charset knob, judged by the reading below */
        $value = $config['charset'] ?? null;
        if ($value === null) {
            return null;
        }

        if (\is_string($value) && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $value) === 1) {
            return $value;
        }

        throw new ConnectionException(
            'Charset must be letters, digits, underscore, dash or dot (1-64 characters), or null'
            . ' (got: ' . (\is_string($value) ? "'{$value}'" : get_debug_type($value)) . ')',
        );
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
