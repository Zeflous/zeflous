<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Override;
use PDO;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\SqlQuery;

/**
 * PDO-backed {@see ConnectionInterface} adapter.
 *
 * - connects lazily on first use (no socket touched for config errors);
 * - maps every \PDOException onto the typed family, chaining the
 *   original -- the caller never sees a driver type;
 * - the nested-transaction SAVEPOINT walk and its isolation rules live
 *   in {@see PdoTransactions}, the FETCH_ASSOC answer's narrowing in
 *   {@see PdoRows}, the statement execution mapping in
 *   {@see PdoStatementRun}, the last-insert identity fold in
 *   {@see PdoIdentity}, the lazy handle in {@see PdoHandle}, the
 *   isolation rules in {@see PdoIsolation} and the closure orchestration
 *   in {@see PdoTransactionRun} -- the adapter owns the port's verbs,
 *   one delegation each.
 */
final readonly class PdoConnection implements ConnectionInterface
{
    private PdoHandle $pdoHandle;

    private PdoTransactions $pdoTransactions;

    private string $driver;

    public function __construct(
        ConnectionConfig $connectionConfig,
        ?PDO $pdo = null,
    ) {
        $this->pdoHandle = new PdoHandle($connectionConfig, $pdo);
        $this->pdoTransactions = new PdoTransactions();
        $this->driver = $connectionConfig->driver;
    }

    #[Override]
    public function execute(SqlQuery $sqlQuery): int
    {
        $pdoStatement = PdoStatementRun::prepare($this->pdoHandle->pdo(), $sqlQuery);
        PdoStatementRun::execute($pdoStatement, $sqlQuery);

        return $pdoStatement->rowCount();
    }

    #[Override]
    public function fetchAll(SqlQuery $sqlQuery): array
    {
        $pdoStatement = PdoStatementRun::prepare($this->pdoHandle->pdo(), $sqlQuery);
        PdoStatementRun::execute($pdoStatement, $sqlQuery);

        return PdoRows::narrow($pdoStatement->fetchAll(PDO::FETCH_ASSOC));
    }

    #[Override]
    public function fetchOne(SqlQuery $sqlQuery): ?array
    {
        return $this->fetchAll($sqlQuery)[0] ?? null;
    }

    #[Override]
    public function lastInsertId(): ?string
    {
        return PdoIdentity::lastInsertId($this->pdoHandle->pdo());
    }

    #[Override]
    public function beginTransaction(?IsolationLevel $isolationLevel = null): void
    {
        $this->pdoTransactions->begin($this->pdoHandle->pdo(), $this->driver, $isolationLevel);
    }

    #[Override]
    public function commit(): void
    {
        $this->pdoTransactions->commit($this->pdoHandle->pdo());
    }

    #[Override]
    public function rollBack(): void
    {
        $this->pdoTransactions->rollBack($this->pdoHandle->pdo());
    }

    #[Override]
    public function transactionLevel(): int
    {
        return $this->pdoTransactions->level();
    }

    #[Override]
    public function transaction(callable $fn, ?IsolationLevel $isolationLevel = null): mixed
    {
        return PdoTransactionRun::around(
            $fn,
            $isolationLevel,
            $this,
            $this->pdoTransactions,
            $this->pdoHandle->pdo(),
            $this->driver,
        );
    }
}
