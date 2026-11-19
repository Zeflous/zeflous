<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Psr\Log\LoggerInterface;
use Throwable;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The unbounded-statement audit's contained orchestration: the
 * security audit hook {@see PdoConnection::execute()} runs when a
 * deliberately unbounded UPDATE/DELETE (the `SqlQuery` flag the
 * query builder's `allowUnbounded()` verb sets) executes -- the
 * emission body itself is the raw fold ({@see
 * PdoUnboundedSignals::emit()}, the {@see PdoQuerySignals::emit()}
 * shape), and this the caller that contains it.
 *
 * Both sinks are optional and independently null: a counter
 * increment through the metrics lane's own contract
 * ({@see MeterInterface}, the `zef.db.unbounded_statement` series
 * carrying the operation word as its dimension) and a WARN record
 * through the PSR-3 logger the deployment's ONE structured log
 * address answers -- the full SQL travelling in the record's
 * context so the event stays greppable in the observability
 * backends, exactly the greppability the slow-hook guard's own
 * records carry. A seam left null is the honest void: no
 * observability, no cost, no allocation -- the same null-means-
 * absent posture the meter's own contract spells.
 *
 * The emission is best-effort, contained the way the measured
 * window's own emissions are ({@see PdoQueryLog::end()} -- "the
 * observation is never worth the query's own answer", issue #787's
 * finding 5): the audit runs AFTER a statement already executed, so
 * a meter or logger that throws would turn the caller's successful
 * write into a post-effect exception -- the record is dropped, the
 * throw swallowed, the statement's own answer untainted.
 *
 * @internal
 */
final class PdoUnboundedAudit
{
    /**
     * Emits the audit, contained: the counter increment and the WARN
     * record through their optional seams, a throwing seam never
     * tainting the statement's own answer.
     *
     * @param null|MeterInterface  $meter    the counter seam, or null when
     *                                       the deployment wires no meter
     * @param null|LoggerInterface $logger   the record seam, or null when
     *                                       the deployment declares no
     *                                       structured log address
     * @param SqlQuery             $sqlQuery the unbounded statement that executed
     * @param int                  $affected the row count the execution reported
     */
    public static function emit(
        ?MeterInterface $meter,
        ?LoggerInterface $logger,
        SqlQuery $sqlQuery,
        int $affected,
    ): void {
        try {
            PdoUnboundedSignals::emit($meter, $logger, $sqlQuery, $affected);
        } catch (Throwable) {
            // Best-effort: the statement already executed, and the
            // observation is never worth the query's own answer.
        }
    }
}
