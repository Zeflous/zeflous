<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Override;
use PDO;
use Psr\Log\LoggerInterface;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IsolationLevel;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * PDO-backed {@see ConnectionInterface} adapter.
 *
 * - connects lazily on first use (no socket touched for config errors);
 * - maps every \PDOException onto the typed family, chaining the
 *   original -- the caller never sees a driver type;
 * - optionally audits `allowUnbounded()` executions: when a
 *   deliberately unbounded UPDATE/DELETE (the `SqlQuery` flag) runs,
 *   an optional {@see MeterInterface} counter increment and/or an
 *   optional {@see LoggerInterface} WARN record is emitted through
 *   {@see PdoUnboundedAudit} for security audit trails (both seams
 *   default to null -- no observability, no cost);
 * - optionally measures its statement runs: an armed {@see PdoQueryLog}
 *   brackets the execute and fetchAll verbs' runs in the finally seam
 *   the throw path shares, answering the per-query record, the
 *   duration observation and the slow verdict's WARN record through
 *   {@see PdoQuerySignals} (the seam defaults to null -- the honest
 *   void: no query log, no clock read, no cost);
 * - the nested-transaction SAVEPOINT walk and its isolation rules live
 *   in {@see PdoTransactions}, the FETCH_ASSOC answer's narrowing in
 *   {@see PdoRows}, the statement execution mapping in
 *   {@see PdoStatementRun}, the data verbs' run orchestration (the
 *   measured window included) in {@see PdoQueryRun}, the last-insert
 *   identity fold in {@see PdoIdentity}, the lazy handle in
 *   {@see PdoHandle}, the isolation rules in {@see PdoIsolation} and
 *   the closure orchestration in {@see PdoTransactionRun} -- the
 *   adapter owns the port's verbs, one delegation each;
 * - every ask verb rides the healing fold ({@see PdoHealing}): a
 *   classified lost-connection failure drops the lazy handle and
 *   resets the savepoint depth with it -- the next ask
 *   re-materialises the socket -- while the classified exception
 *   propagates untouched (heal-and-propagate, never blind replay:
 *   the caller's retry policy decides whether the next ask lands).
 */
final readonly class PdoConnection implements ConnectionInterface
{
    private PdoHandle $pdoHandle;

    private PdoTransactions $pdoTransactions;

    private string $driver;

    public function __construct(
        ConnectionConfig $connectionConfig,
        ?PDO $pdo = null,
        private ?MeterInterface $meter = null,
        private ?LoggerInterface $auditLogger = null,
        private ?PdoQueryLog $pdoQueryLog = null,
    ) {
        $this->pdoHandle = new PdoHandle($connectionConfig, $pdo);
        $this->pdoTransactions = new PdoTransactions();
        $this->driver = $connectionConfig->driver;
    }

    #[Override]
    public function execute(SqlQuery $sqlQuery): int
    {
        return PdoHealing::around(
            $this->pdoHandle,
            $this->pdoTransactions,
            fn (): int => PdoQueryRun::execute(
                $this->pdoHandle->pdo(),
                $this->meter,
                $this->auditLogger,
                $this->pdoQueryLog,
                $sqlQuery,
            ),
        );
    }

    #[Override]
    public function fetchAll(SqlQuery $sqlQuery): array
    {
        return PdoHealing::around(
            $this->pdoHandle,
            $this->pdoTransactions,
            fn (): array => PdoQueryRun::fetchAll($this->pdoHandle->pdo(), $this->pdoQueryLog, $sqlQuery),
        );
    }

    #[Override]
    public function fetchOne(SqlQuery $sqlQuery): ?array
    {
        return $this->fetchAll($sqlQuery)[0] ?? null;
    }

    #[Override]
    public function lastInsertId(): ?string
    {
        return PdoHealing::around(
            $this->pdoHandle,
            $this->pdoTransactions,
            fn (): ?string => PdoIdentity::lastInsertId($this->pdoHandle->pdo()),
        );
    }

    #[Override]
    public function beginTransaction(?IsolationLevel $isolationLevel = null): void
    {
        PdoHealing::around(
            $this->pdoHandle,
            $this->pdoTransactions,
            fn (): null => $this->pdoTransactions->begin(
                $this->pdoHandle->pdo(),
                $this->driver,
                $isolationLevel,
            ),
        );
    }

    #[Override]
    public function commit(): void
    {
        PdoHealing::around(
            $this->pdoHandle,
            $this->pdoTransactions,
            fn (): null => $this->pdoTransactions->commit($this->pdoHandle->pdo()),
        );
    }

    #[Override]
    public function rollBack(): void
    {
        PdoHealing::around(
            $this->pdoHandle,
            $this->pdoTransactions,
            fn (): null => $this->pdoTransactions->rollBack($this->pdoHandle->pdo()),
        );
    }

    #[Override]
    public function transactionLevel(): int
    {
        return $this->pdoTransactions->level();
    }

    #[Override]
    public function transaction(callable $fn, ?IsolationLevel $isolationLevel = null): mixed
    {
        return PdoHealing::around(
            $this->pdoHandle,
            $this->pdoTransactions,
            fn (): mixed => PdoTransactionRun::around(
                $fn,
                $isolationLevel,
                $this,
                $this->pdoTransactions,
                $this->pdoHandle->pdo(),
                $this->driver,
            ),
        );
    }
}
