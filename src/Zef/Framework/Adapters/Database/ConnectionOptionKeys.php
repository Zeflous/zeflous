<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;

/**
 * The connection options' unknown-key census: every key outside the
 * two known options halts with the known list spelled, so a silent
 * typo can never fall through to the silent posture.
 *
 * @internal
 */
final class ConnectionOptionKeys
{
    /**
     * @param array<mixed, mixed> $value
     */
    public static function rejectUnknown(array $value): void
    {
        foreach (array_keys($value) as $key) {
            if (\is_string($key) && \in_array($key, ['persistent', 'timeout'], true)) {
                continue;
            }

            throw new ConnectionException(
                "Unknown connection option '" . (\is_string($key) ? $key : get_debug_type($key))
                . "' (allowed: persistent, timeout).",
            );
        }
    }
}
