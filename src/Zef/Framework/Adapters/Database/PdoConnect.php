<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use PDOException;
use Zef\Framework\Database\ConnectionException;

/**
 * The handle-opening fold: one {@see PDO} over the validated
 * configuration's DSN and the attribute set {@see PdoAttributes}
 * spells, its driver failure mapped onto the typed family with the
 * driver named.
 *
 * @internal
 */
final class PdoConnect
{
    /**
     * Connects one handle over the validated configuration.
     *
     * @throws ConnectionException when the driver refuses the connection
     */
    public static function connect(ConnectionConfig $connectionConfig): PDO
    {
        try {
            return new PDO(
                $connectionConfig->dsn(),
                $connectionConfig->user,
                $connectionConfig->password,
                PdoAttributes::of($connectionConfig),
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
