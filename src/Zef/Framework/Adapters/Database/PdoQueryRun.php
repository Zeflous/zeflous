<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use Psr\Log\LoggerInterface;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The data verbs' orchestration: the statement run, its observability
 * emissions answering around it -- the {@see PdoTransactionRun}
 * lineage over the connection's two data verbs, so the verbs that
 * delegate here keep their own volume honest, one delegation each.
 *
 * The measured window brackets the statement's OWN work only: the
 * opening reading before it, the emission in the finally seam the
 * throw path shares (a statement that answered slowly records, and a
 * statement that threw slowly records and then propagates its throw
 * untouched), and the unbounded-statement audit emission AFTER the
 * window -- an emission is never statement work, and a duration that
 * measured its own record would lie about the statement. The disarmed
 * connection's window is the inert one: no clock read (the nullsafe
 * bracket's `?? 0` instant the closing edge never reads), no
 * allocation, the timing family's own no-observable-difference
 * posture.
 *
 * @internal
 */
final class PdoQueryRun
{
    /**
     * The execute verb's orchestration: the statement pair and the
     * affected-row count inside the window, the unbounded-statement
     * audit emission after it.
     */
    public static function execute(
        PDO $pdo,
        ?MeterInterface $meter,
        ?LoggerInterface $auditLogger,
        ?PdoQueryLog $pdoQueryLog,
        SqlQuery $sqlQuery,
    ): int {
        $startNs = $pdoQueryLog?->begin() ?? 0;

        try {
            $affected = PdoStatementRun::execute(PdoStatementRun::prepare($pdo, $sqlQuery), $sqlQuery)->rowCount();
        } finally {
            $pdoQueryLog?->end($sqlQuery, $startNs);
        }

        if ($sqlQuery->unbounded) {
            PdoUnboundedAudit::emit($meter, $auditLogger, $sqlQuery, $affected);
        }

        return $affected;
    }

    /**
     * The fetchAll verb's orchestration: the statement pair and the
     * narrowed FETCH_ASSOC answer inside the window.
     *
     * @return list<array<string, mixed>> the narrowed rows
     */
    public static function fetchAll(PDO $pdo, ?PdoQueryLog $pdoQueryLog, SqlQuery $sqlQuery): array
    {
        $startNs = $pdoQueryLog?->begin() ?? 0;

        try {
            return PdoRows::narrow(
                PdoStatementRun::execute(PdoStatementRun::prepare($pdo, $sqlQuery), $sqlQuery)
                    ->fetchAll(PDO::FETCH_ASSOC),
            );
        } finally {
            $pdoQueryLog?->end($sqlQuery, $startNs);
        }
    }
}
