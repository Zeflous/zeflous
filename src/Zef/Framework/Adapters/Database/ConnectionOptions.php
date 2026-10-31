<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;

/**
 * The connection options map's strict reading: the two known options
 * (`persistent`, `timeout`) each validated by its own fold, every
 * unknown key refused with the known list spelled, and the accepted
 * map answered in the options' own shape.
 *
 * @internal
 */
final class ConnectionOptions
{
    /**
     * @return array{persistent?: bool, timeout?: float|int}
     */
    public static function read(mixed $value): array
    {
        if (\is_array($value)) {
            return self::readKnown($value);
        }

        throw new ConnectionException('Options must be an array.');
    }

    /**
     * Reads the two known options from the validated map.
     *
     * @param array<mixed, mixed> $value
     *
     * @return array{persistent?: bool, timeout?: float|int}
     */
    private static function readKnown(array $value): array
    {
        $normalized = [];
        if (\array_key_exists('persistent', $value)) {
            $normalized['persistent'] = self::readPersistent($value['persistent']);
        }

        if (\array_key_exists('timeout', $value)) {
            $normalized['timeout'] = self::readTimeout($value['timeout']);
        }

        ConnectionOptionKeys::rejectUnknown($value);

        return $normalized;
    }

    /**
     * The persistent option: a bool.
     */
    private static function readPersistent(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        throw new ConnectionException('Option persistent must be a bool.');
    }

    /**
     * The timeout option: a positive number of seconds, int or float.
     */
    private static function readTimeout(mixed $value): float|int
    {
        if (\is_int($value) || \is_float($value)) {
            if ($value <= 0) {
                throw new ConnectionException('Option timeout must be greater than zero.');
            }

            return $value;
        }

        throw new ConnectionException('Option timeout must be an int or float.');
    }
}
