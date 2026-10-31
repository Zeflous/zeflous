<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;

/**
 * The connection attributes the configuration spells: the fail-loud
 * error mode, the FETCH_ASSOC answer shape and the two native-binding
 * switches every handle carries, plus the persistent flag and the
 * timeout the options map may arm. One pure fold over the config --
 * {@see PdoConnect} hands it to the PDO constructor verbatim.
 *
 * @internal
 */
final class PdoAttributes
{
    /**
     * @return array<int, bool|int> the driver attributes the handle opens with
     */
    public static function of(ConnectionConfig $connectionConfig): array
    {
        $attributes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if (($connectionConfig->options['persistent'] ?? false) === true) {
            $attributes[PDO::ATTR_PERSISTENT] = true;
        }

        $timeout = $connectionConfig->options['timeout'] ?? null;
        if (\is_int($timeout) || \is_float($timeout)) {
            $attributes[PDO::ATTR_TIMEOUT] = (int) ceil((float) $timeout);
        }

        return $attributes;
    }
}
