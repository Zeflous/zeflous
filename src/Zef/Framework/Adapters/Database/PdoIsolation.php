<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use PDOException;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\TransactionException;

/**
 * The isolation-request rules: the level is applied ONCE, via
 * `SET TRANSACTION ISOLATION LEVEL`, immediately before the outermost
 * BEGIN; a request on a nested savepoint refuses loudly (savepoints
 * cannot change isolation on most RDBMS); SQLite rejects the concept
 * outright.
 *
 * @internal
 */
final class PdoIsolation
{
    /**
     * @throws TransactionException when nested and $isolation requested
     * @throws ConnectionException  on SQLite with $isolation requested
     */
    public static function apply(PDO $pdo, string $driver, ?IsolationLevel $isolationLevel, int $level): void
    {
        if (!$isolationLevel instanceof IsolationLevel) {
            return;
        }

        if ($level > 0) {
            throw new TransactionException(
                'Isolation level may only be requested on the outermost transaction'
                . ' (current level: ' . $level . ').',
            );
        }

        if ($driver === 'sqlite') {
            throw new ConnectionException(
                'SQLite does not support isolation levels; pass null instead of ' . $isolationLevel->value . '.',
            );
        }

        self::exec($pdo, 'SET TRANSACTION ISOLATION LEVEL ' . $isolationLevel->value);
    }

    /**
     * Opens the nested savepoint for the given depth (depth 1 spells
     * `zef_sp2`).
     */
    public static function savepoint(PDO $pdo, int $depth): void
    {
        self::exec($pdo, 'SAVEPOINT zef_sp' . ($depth + 1));
    }

    /**
     * Releases the savepoint a nested commit closes.
     */
    public static function release(PDO $pdo, int $depth): void
    {
        self::exec($pdo, 'RELEASE SAVEPOINT zef_sp' . $depth);
    }

    /**
     * Restores the savepoint a nested rollback returns to.
     */
    public static function rollbackTo(PDO $pdo, int $depth): void
    {
        self::exec($pdo, 'ROLLBACK TO SAVEPOINT zef_sp' . $depth);
    }

    /**
     * One administrative statement, its driver failure mapped onto the
     * typed family with the SQL spelled.
     */
    private static function exec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (PDOException $pdoException) {
            throw new QueryException(
                'Execution failed: ' . $pdoException->getMessage() . ' (sql: ' . $sql . ')',
                0,
                $pdoException,
            );
        }
    }
}
