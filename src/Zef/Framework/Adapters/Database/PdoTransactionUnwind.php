<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use PDO;
use Throwable;

/**
 * The best-effort rollback the transaction run's catch lane owes a
 * failed closure: the level-zero guard, the driver-refusal recording,
 * and nothing else -- a fold the class's difficulty ceiling forced out
 * of {@see PdoTransactionRun} (20.50 against the 20 the architecture
 * gate pins), the same decomposition the fold's own family spells when
 * a unit's questions stop fitting one class.
 *
 * The level-zero guard is the mid-flight heal's own shape: a classified
 * lost-connection failure inside the closure has already dropped the
 * handle and reset the depth with it ({@see PdoHealing}), so the engine
 * has ended the transaction and no rollback is ours to run -- attempting
 * one would only answer the depth machine's level-zero refusal,
 * replacing the original driver failure with a TransactionException no
 * retry policy can classify (issue #777).
 *
 * A rollback that itself fails (the driver refuses it on a live
 * transaction) is recorded on the SAPI diagnostic lane -- the same
 * error_log default the middleware family's diagnostic sinks carry --
 * and swallowed: the caller receives the closure's original failure,
 * and the depth machine keeps its honest level for the next explicit
 * verb to answer.
 *
 * @internal
 */
final class PdoTransactionUnwind
{
    /**
     * Rolls back the transaction a failed closure left open, quietly:
     * nothing this fold answers may replace or precede the closure's own
     * throwable -- the run rethrows it the moment this returns.
     */
    public static function quietly(PdoTransactions $pdoTransactions, PDO $pdo): void
    {
        if ($pdoTransactions->level() === 0) {
            return;
        }

        try {
            $pdoTransactions->rollBack($pdo);
        } catch (Throwable $throwable) {
            error_log('ZEF transaction unwind failure: ' . $throwable::class);
        }
    }
}
