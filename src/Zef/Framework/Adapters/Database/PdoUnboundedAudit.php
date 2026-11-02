<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Psr\Log\LoggerInterface;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The unbounded-statement audit emission: the security audit hook
 * {@see PdoConnection::execute()} runs when a deliberately
 * unbounded UPDATE/DELETE (the `SqlQuery` flag the query builder's
 * `allowUnbounded()` verb sets) executes -- the reference's own
 * optional-seam posture, arrived with the wiring slice that gave
 * the seams their first live reader ({@see DatabaseWiring}).
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
 * The operation word folds from the statement's own head: an
 * UPDATE-prefixed statement audits as 'update', every other
 * unbounded statement as 'delete' -- the two verbs the lean
 * builder's unbounded surface carries.
 *
 * @internal
 */
final class PdoUnboundedAudit
{
    /**
     * The meter series the emission counts against: the framework's
     * own dotted namespace, the unbounded-statement counter a
     * deployment's security audit trail reads.
     */
    private const string SERIES = 'zef.db.unbounded_statement';

    /**
     * Emits the audit: the counter increment and the WARN record,
     * each through its own optional seam.
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
        $operation = self::operation($sqlQuery->sql);

        $meter?->increment(self::SERIES, 1, ['op' => $operation]);

        $logger?->warning(
            'Unbounded ' . $operation . ' executed (allowUnbounded): ' . $affected . ' row(s) affected',
            ['sql' => $sqlQuery->sql, 'rows' => $affected, 'op' => $operation],
        );
    }

    /**
     * The statement's operation word, folded from its own head: an
     * UPDATE-prefixed statement audits as 'update', every other
     * unbounded statement as 'delete'.
     */
    private static function operation(string $sql): string
    {
        return str_starts_with(strtoupper(ltrim($sql)), 'UPDATE') ? 'update' : 'delete';
    }
}
