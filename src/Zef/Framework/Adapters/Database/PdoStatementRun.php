<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use PDOException;
use PDOStatement;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;

/**
 * One prepared statement's execution, its driver failure mapped onto
 * the typed family with the SQL spelled -- the single mapping both the
 * execute and fetch verbs run through.
 *
 * @internal
 */
final class PdoStatementRun
{
    /**
     * Prepares the statement, its driver failure mapped onto the typed
     * family with the SQL spelled -- and the engine's falsy spelling
     * (ERRMODE_EXCEPTION throws instead, but the signature still
     * spells `PDOStatement|false`) refused the same fail-closed way.
     */
    public static function prepare(PDO $pdo, SqlQuery $sqlQuery): PDOStatement
    {
        try {
            $pdoStatement = $pdo->prepare($sqlQuery->sql);
        } catch (PDOException $pdoException) {
            throw new QueryException(
                'Preparation failed: ' . $pdoException->getMessage() . ' (sql: ' . $sqlQuery->sql . ')',
                (int) $pdoException->getCode(),
                $pdoException,
            );
        }

        if ($pdoStatement === false) {
            throw new QueryException('Preparation failed without an exception (sql: ' . $sqlQuery->sql . ').');
        }

        return $pdoStatement;
    }

    /**
     * Executes the statement with the query's positional values.
     */
    public static function execute(PDOStatement $pdoStatement, SqlQuery $sqlQuery): void
    {
        try {
            $pdoStatement->execute($sqlQuery->params);
        } catch (PDOException $pdoException) {
            throw new QueryException(
                'Execution failed: ' . $pdoException->getMessage() . ' (sql: ' . $sqlQuery->sql . ')',
                (int) $pdoException->getCode(),
                $pdoException,
            );
        }
    }
}
