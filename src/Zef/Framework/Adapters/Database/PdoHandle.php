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
}
