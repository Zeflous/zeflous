<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use Psr\Log\LoggerInterface;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The bulk data verbs' orchestration: the statement run, its observability
 * emissions answering around it -- the {@see PdoTransactionRun}
 * lineage over the connection's execute and fetchAll verbs, so the
 * verbs that delegate here keep their own volume honest, one
 * delegation each (the single-row fetch verb lives its own fold,
 * {@see PdoFetchOne}, the archeology ceiling's own demand).
 *
 * The measured window brackets the statement's OWN work only: the
 * opening reading before it, the emission in the finally seam the
 * throw path shares (a statement that answered slowly records, and a
 * statement that threw slowly records and then propagates its throw
 * untouched), and the unbounded-statement audit emission AFTER the
 * window -- an emission is never statement work, and a duration that
 * measured its own record would lie about the statement. The
 * request-scoped counter's feeder ({@see PdoQueryCount::feed()})
 * rides the SAME finally seam the window's closing edge does: one
 * increment per executed statement, the throw path sharing it -- a
 * query the request paid is a query the toolbar counts, whichever
 * way the statement ended. The disarmed
 * connection's window is the inert one: no clock read (the nullsafe
 * bracket's `?? 0` instant the closing edge never reads), no
 * allocation, the timing family's own no-observable-difference
 * posture -- and the feederless run pays no count either (the null
 * fold's honest void).
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
        ?PdoQueryCount $pdoQueryCount = null,
    ): int {
        $startNs = $pdoQueryLog?->begin() ?? 0;

        try {
            $affected = PdoStatementRun::execute(PdoStatementRun::prepare($pdo, $sqlQuery), $sqlQuery)->rowCount();
        } finally {
            $pdoQueryLog?->end($sqlQuery, $startNs);
            $pdoQueryCount?->feed();
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
    public static function fetchAll(
        PDO $pdo,
        ?PdoQueryLog $pdoQueryLog,
        SqlQuery $sqlQuery,
        ?PdoQueryCount $pdoQueryCount = null,
    ): array
    {
        $startNs = $pdoQueryLog?->begin() ?? 0;

        try {
            return PdoRows::narrow(
                PdoStatementRun::execute(PdoStatementRun::prepare($pdo, $sqlQuery), $sqlQuery)
                    ->fetchAll(PDO::FETCH_ASSOC),
            );
        } finally {
            $pdoQueryLog?->end($sqlQuery, $startNs);
            $pdoQueryCount?->feed();
        }
    }
}
