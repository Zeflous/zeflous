<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use Throwable;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IsolationLevel;

/**
 * The transaction() closure's orchestration: begin (isolation honoured
 * only when this call opens the outermost transaction), the closure's
 * answer, rollback on any throwable and the commit -- the four steps
 * every managed run walks, in order.
 *
 * @internal
 */
final class PdoTransactionRun
{
    /**
     * @template T
     *
     * @param callable(ConnectionInterface): T $fn
     *
     * @return T
     */
    public static function around(
        callable $fn,
        ?IsolationLevel $isolationLevel,
        ConnectionInterface $connection,
        PdoTransactions $pdoTransactions,
        PDO $pdo,
        string $driver,
    ): mixed {
        $outermost = $pdoTransactions->level() === 0;
        $pdoTransactions->begin($pdo, $driver, $outermost ? $isolationLevel : null);

        try {
            $result = $fn($connection);
        } catch (Throwable $throwable) {
            $pdoTransactions->rollBack($pdo);

            throw $throwable;
        }

        $pdoTransactions->commit($pdo);

        return $result;
    }
}
