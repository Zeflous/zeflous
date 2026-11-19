<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Psr\Log\LoggerInterface;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * The unbounded-statement audit's own emission: the counter increment
 * and the WARN record through their optional seams -- the raw fold the
 * contained caller ({@see PdoUnboundedAudit::emit()}) runs AFTER a
 * deliberately unbounded statement executed, the
 * {@see PdoQuerySignals::emit()} shape the query-log family spells:
 * the emission body itself carries no containment (its unit pins call
 * it directly, so a seam that throws answers its own error -- the
 * nullseam pins key on the throw), and the containment lives at the
 * caller the adapter's execute verb walks ("the observation is never
 * worth the query's own answer").
 *
 * The operation word folds from the statement's own head: an
 * UPDATE-prefixed statement audits as 'update', every other unbounded
 * statement as 'delete' -- the two verbs the lean builder's unbounded
 * surface carries.
 *
 * @internal
 */
final class PdoUnboundedSignals
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
        return str_starts_with(mb_strtoupper(mb_ltrim($sql)), 'UPDATE') ? 'update' : 'delete';
    }
}
