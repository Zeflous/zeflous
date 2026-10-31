<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use PDOException;
use Zef\Framework\Database\ConnectionException;

/**
 * The lazy connect fold: the driver attributes the adapter arms every
 * handle with (exceptions, native prepares, associative fetches, the
 * optional persistence and timeout) and the constructor call whose
 * failure maps onto the typed family with the driver named.
 *
 * @internal
 */
final class PdoConnect
{
    /**
     * Connects one handle over the validated configuration.
     */
    public static function connect(ConnectionConfig $connectionConfig): PDO
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

        try {
            return new PDO(
                $connectionConfig->dsn(),
                $connectionConfig->user,
                $connectionConfig->password,
                $attributes,
            );
        } catch (PDOException $pdoException) {
            throw new ConnectionException(
                'Connection failed (' . $connectionConfig->driver . '): ' . $pdoException->getMessage(),
                (int) $pdoException->getCode(),
                $pdoException,
            );
        }
    }
}
