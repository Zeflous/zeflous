<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;

/**
 * The lazy handle fold: the one owner of the connect-on-first-use
 * discipline -- no socket is touched for configuration errors, and a
 * preset handle answers as given.
 *
 * @internal
 */
final class PdoHandle
{
    public function __construct(private readonly ConnectionConfig $connectionConfig, private ?PDO $pdo)
    {
    }

    /**
     * The live handle, connected on first use.
     */
    public function pdo(): PDO
    {
        $this->pdo ??= PdoConnect::connect($this->connectionConfig);

        return $this->pdo;
    }

    /**
     * Drops the materialised handle: the next ask re-materialises the
     * socket over the same connection identity -- the lazy singleton's
     * own doctrine, replayed at heal time by {@see PdoHealing} (the
     * classified lost-connection fold the only caller, beside which the
     * transaction depth's reset always rides: a depth that survived its
     * own handle would answer every later beginTransaction a phantom
     * level over a socket whose engine already ended the transaction).
     */
    public function drop(): void
    {
        $this->pdo = null;
    }
}
