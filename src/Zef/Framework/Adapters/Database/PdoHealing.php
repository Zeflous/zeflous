<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Closure;
use PDOException;
use Throwable;
use Zef\Framework\Database\RetryTaxonomy;
use Zef\Framework\Database\UnitOfWorkRetryPolicy;

/**
 * The healing fold over the lazy handle: on a classified
 * lost-connection failure the ask's socket is dropped and the
 * savepoint depth resets with it -- the next ask re-materialises the
 * socket, the classified exception propagating untouched (never
 * wrapped, never swallowed, never answered as a success: an
 * adapter-side blind replay of an INSERT can double-write, and the
 * caller's retry policy -- the UnitOfWork's or the host's own --
 * decides whether the NEXT ask lands).
 *
 * The evidence the judgement reads is the driver failure the adapter
 * saw: the thrown throwable itself when the ask escaped a raw
 * PDOException (the identity verb's unmapped spelling), else its
 * one-hop previous -- the typed family's own contract, every driver
 * failure mapped with the original chained. The verdict runs on the
 * engine-side taxonomy ({@see RetryTaxonomy} over
 * {@see RetryCandidates} and {@see RetryMessages}) reached one-way
 * from the adapter, the same one-way reach the meter contract
 * carries -- the LOST-CONNECTION SUBSET only: the codes that mean the
 * socket died, and the lost-connection message patterns the retry
 * policy's default already spells. A deadlock, a lock-wait timeout or
 * a serialization failure is deliberately NOT a lost connection -- the
 * socket stays alive, the retry verdict owns those, and dropping a
 * healthy handle for them would reconnect for nobody.
 *
 * @internal
 */
final class PdoHealing
{
    /**
     * The lost-connection code lane: the driver codes that mean the
     * socket is gone -- 2006 (CR_SERVER_GONE / CR_SERVER_LOST: MySQL
     * server has gone away, lost during query) and 1040
     * (ER_CON_COUNT_ERROR: the server shed the connection under load).
     * The taxonomy's deadlock/lock/serialization family is
     * deliberately absent -- those failures leave the socket alive --
     * and the libpq spellings need no code: the message lane below
     * answers every one of them.
     */
    private const array LOST_CONNECTION_SQL_STATES = ['2006', '1040'];

    /**
     * Brackets one ask verb of the connection port: the ask runs
     * untouched, and only a classified lost-connection failure heals
     * -- the handle dropped, the depth reset, the SAME throwable
     * instance re-thrown.
     *
     * @template T
     *
     * @param Closure(): T $ask the verb's own work, handle
     *                          resolution included
     *
     * @return T the ask's answer, verbatim
     */
    public static function around(
        PdoHandle $pdoHandle,
        PdoTransactions $pdoTransactions,
        Closure $ask,
    ): mixed {
        try {
            return $ask();
        } catch (Throwable $throwable) {
            if (self::isLostConnection($throwable)) {
                $pdoHandle->drop();
                $pdoTransactions->reset();
            }

            throw $throwable;
        }
    }

    /**
     * True when the throwable the ask threw carries lost-connection
     * evidence the taxonomy classifies -- the fold's gate: only the
     * driver failure itself, raw or one-hop chained, is ever
     * classifiable (the closure's own domain throwables carry no
     * driver evidence, whatever their message spells).
     */
    private static function isLostConnection(Throwable $throwable): bool
    {
        $evidence = self::driverEvidence($throwable);

        return $evidence instanceof PDOException && RetryTaxonomy::matches(
            $evidence,
            [PDOException::class],
            self::LOST_CONNECTION_SQL_STATES,
            UnitOfWorkRetryPolicy::DEFAULT_RETRYABLE_MESSAGE_PATTERNS,
        );
    }

    /**
     * The driver failure the adapter saw, when the throwable carries
     * one: the thrown PDOException itself (the identity verb's raw
     * escape), else the one-hop previous the typed family's mapping
     * chains -- never a deeper walk, deliberately: the adapter's own
     * mapping is one hop by contract, and a causality walk past it
     * would classify stale evidence from failures long healed.
     */
    private static function driverEvidence(Throwable $throwable): ?PDOException
    {
        if ($throwable instanceof PDOException) {
            return $throwable;
        }

        $previous = $throwable->getPrevious();

        return $previous instanceof PDOException ? $previous : null;
    }
}
