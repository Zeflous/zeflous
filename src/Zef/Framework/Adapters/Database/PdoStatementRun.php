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
     * Executes the statement with the query's positional values, each
     * bound at its own engine-side type through {@see PdoParamTypes}
     * -- a boolean the engine's own zero-or-one, never the empty
     * string the array-lane binding stringifies `false` into --
     * answering the statement it ran.
     */
    public static function execute(PDOStatement $pdoStatement, SqlQuery $sqlQuery): PDOStatement
    {
        try {
            /** @var mixed $value one positional value, its engine-side type read by the fold below */
            foreach ($sqlQuery->params as $position => $value) {
                $pdoStatement->bindValue($position + 1, $value, PdoParamTypes::of($value));
            }

            $pdoStatement->execute();
        } catch (PDOException $pdoException) {
            throw new QueryException(
                'Execution failed: ' . $pdoException->getMessage() . ' (sql: ' . $sqlQuery->sql . ')',
                (int) $pdoException->getCode(),
                $pdoException,
            );
        }

        return $pdoStatement;
    }
}
