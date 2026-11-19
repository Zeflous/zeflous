<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use Zef\Framework\Database\SqlQuery;

/**
 * The single-row fetch verb's orchestration: the statement pair and
 * ONE cursor-stepped row inside the measured window -- the single row
 * the port's fetchOne verb promises, taken from the cursor's own
 * `fetch()` step (issue #787's finding 3: the fetchAll-then-first
 * spelling materialised the whole result set into memory to answer
 * its first row -- a million-row SELECT paid a million rows for
 * one). The narrowed row rides the same {@see PdoRows} contract the
 * list verb holds, one row at a time.
 *
 * The window is the {@see PdoQueryRun} lineage's own: the opening
 * reading before the statement, the emission in the finally seam the
 * throw path shares -- a fetch that answered slowly records, and a
 * fetch that threw slowly records and then propagates its throw
 * untouched. The disarmed connection's window is the inert one: no
 * clock read, no allocation, the timing family's own
 * no-observable-difference posture.
 *
 * @internal
 */
final class PdoFetchOne
{
    /**
     * The fetchOne verb: the statement pair, ONE cursor-stepped row,
     * the window bracketed around it.
     *
     * @return null|array<string, null|bool|float|int|string> the first row, or null when the answer is empty
     */
    public static function fetch(PDO $pdo, ?PdoQueryLog $pdoQueryLog, SqlQuery $sqlQuery): ?array
    {
        $startNs = $pdoQueryLog?->begin() ?? 0;

        try {
            /**
             * @var array<string, mixed>|false $row the cursor-stepped
             *                                 FETCH_ASSOC row, false
             *                                 when the answer is empty
             */
            $row = PdoStatementRun::execute(PdoStatementRun::prepare($pdo, $sqlQuery), $sqlQuery)
                ->fetch(PDO::FETCH_ASSOC)
            ;

            return $row === false ? null : PdoRows::narrow([$row])[0];
        } finally {
            $pdoQueryLog?->end($sqlQuery, $startNs);
        }
    }
}
