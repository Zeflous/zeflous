<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;

/**
 * The network host's strict reading: non-empty, whitespace-free, at
 * most 255 bytes -- one grammar the single regex spells.
 *
 * @internal
 */
final class ConnectionHost
{
    /**
     * @param array<string, mixed> $config
     */
    public static function read(array $config, string $driver): string
    {
        /** @var mixed $value the host knob, judged by the reading below */
        $value = $config['host'] ?? null;
        if (\is_string($value) && preg_match('/^\S{1,255}$/', $value) === 1) {
            return $value;
        }

        throw new ConnectionException(
            "Host must be a non-empty string without whitespace for driver '{$driver}'.",
        );
    }
}
