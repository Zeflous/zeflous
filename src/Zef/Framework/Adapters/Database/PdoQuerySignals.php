<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Psr\Log\LoggerInterface;
use Zef\Framework\Database\QueryLogPolicy;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The query-log emission: the two records and the two meter signals
 * one executed statement answers -- the per-query DEBUG record and the
 * duration observation every measured statement carries, and the slow
 * verdict's WARN record and counter the statements that strictly
 * exceed the configured bar add on top -- the unbounded-statement
 * audit emission's own {@see PdoUnboundedAudit::emit()} shape over the
 * slow-request family's own record doctrines.
 *
 * The per-query record is the family's primary deliverable (the
 * reference's SQL-logger shape: one record per executed statement,
 * the SQL travelling in the record's context so the event stays
 * greppable in the observability backends), and the slow verdict
 * rides alongside rather than replacing it -- two questions, two
 * records: the per-query record says the statement ran and how long
 * it took, the slow record flags the bar it crossed, and an operator
 * tailing the one sink reads them adjacent. The parameters are
 * deliberately NOT in either record's context: the statement's text
 * is grammar while the bound values are payload, and the tree's
 * privacy-by-default posture refuses payload in records -- the same
 * refusal the slow-request family's own record spells over request
 * bodies and query strings.
 *
 * Both levels are constants, not knobs: a knob would let a deployment
 * spell the same fact at another level, fragmenting the one
 * vocabulary every consumer of the records filters on. The
 * milliseconds a log consumer graphs render at the timing family's
 * own two-decimal precision; the meter's observation carries the
 * duration in seconds, the base unit the router lane's own duration
 * series established ({@see \Zef\Framework\Middleware\RouteMatchTelemetry}),
 * over the dotted dimension key the telemetry lane's series carry.
 *
 * @internal
 */
final class PdoQuerySignals
{
    /**
     * The meter series the measured statements observe their durations
     * against: the framework's own dotted namespace, the per-query
     * histogram-style series (a count plus a sum per operation word)
     * an operator's duration distribution reads.
     */
    private const string DURATION_SECONDS = 'zef.db.query.duration_seconds';

    /**
     * The meter series the slow verdict counts against: the counter a
     * deployment's slow-query rate reads, the operation word as its
     * only dimension.
     */
    private const string SLOW_TOTAL = 'zef.db.slow_query.total';

    /**
     * The dimension key the operation word travels under: the dotted
     * spelling the telemetry lane's own series carry.
     */
    private const string OPERATION = 'db.statement.operation';

    /**
     * The per-query record's message: the family's own spelling, so a
     * stored record's origin is readable without its context.
     */
    private const string QUERY_MESSAGE = 'Query executed';

    /**
     * The slow record's message: the family's own pathology spelling,
     * the slow-request family's own message-lane doctrine.
     */
    private const string SLOW_MESSAGE = 'Slow query';

    /**
     * The nanoseconds one second carries -- the meter's duration series
     * renders in the base unit the router lane's own series spell.
     */
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    /**
     * Emits the query log: the duration observation and the per-query
     * DEBUG record every measured statement answers, and the slow
     * counter and WARN record the statements that strictly exceed the
     * policy's bar add on top.
     *
     * @param null|MeterInterface  $meter          the metrics seam, or null
     *                                             when the deployment wires
     *                                             no meter
     * @param null|LoggerInterface $logger         the record seam, or null
     *                                             when the deployment
     *                                             declares no structured
     *                                             log address
     * @param SqlQuery             $sqlQuery       the statement that executed
     * @param int                  $elapsedNs      the statement's monotonic
     *                                             span, in nanoseconds
     * @param QueryLogPolicy       $queryLogPolicy the bar the span was
     *                                             judged against
     */
    public static function emit(
        ?MeterInterface $meter,
        ?LoggerInterface $logger,
        SqlQuery $sqlQuery,
        int $elapsedNs,
        QueryLogPolicy $queryLogPolicy,
    ): void {
        $operation = PdoQueryOperation::word($sqlQuery->sql);
        $dimensions = [self::OPERATION => $operation];

        $meter?->observe(
            self::DURATION_SECONDS,
            $elapsedNs / self::NANOSECONDS_PER_SECOND,
            $dimensions,
        );

        $logger?->debug(self::QUERY_MESSAGE, self::queryContext($sqlQuery, $elapsedNs, $operation));

        if (!$queryLogPolicy->exceededBy($elapsedNs)) {
            return;
        }

        $meter?->increment(self::SLOW_TOTAL, 1, $dimensions);

        $logger?->warning(self::SLOW_MESSAGE, self::slowContext($sqlQuery, $elapsedNs, $queryLogPolicy, $operation));
    }

    /**
     * The per-query record's context: the statement's own text, its
     * duration at the rendering precision a log consumer graphs, and
     * its operation word -- no bound values, the privacy posture the
     * family's records carry.
     *
     * @return array<string, mixed> the DEBUG record's context
     */
    private static function queryContext(SqlQuery $sqlQuery, int $elapsedNs, string $operation): array
    {
        return [
            'sql' => $sqlQuery->sql,
            'elapsed_ms' => round($elapsedNs / 1000000, 2),
            'op' => $operation,
        ];
    }

    /**
     * The slow record's context: the per-query record's own facts plus
     * the threshold the span was judged against -- the number an
     * operator reads to know which knob was in force when the record
     * was written, the slow-request family's own threshold fact.
     *
     * @return array<string, mixed> the WARN record's context
     */
    private static function slowContext(
        SqlQuery $sqlQuery,
        int $elapsedNs,
        QueryLogPolicy $queryLogPolicy,
        string $operation,
    ): array {
        return [
            'sql' => $sqlQuery->sql,
            'elapsed_ms' => round($elapsedNs / 1000000, 2),
            'threshold_ms' => $queryLogPolicy->thresholdMs,
            'op' => $operation,
        ];
    }
}
