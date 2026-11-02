<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Closure;

/**
 * UoW-lite: a deferred-write queue with transactional flush semantics.
 *
 * This is deliberately NOT a full Unit of Work — the framework has no ORM,
 * so there are no entities to track. What command handlers actually need
 * is a place to COLLECT the writes a command produces (via {@see QueryBuilder}
 * or raw {@see SqlQuery}) and execute them ATOMICALLY as part of the
 * command's transaction, in FIFO order, exactly once:
 *
 * - handlers call {@see record()} / {@see recordQuery()} during dispatch
 *   (or anywhere in the scope);
 * - the transaction owner flushes the queue on the transaction's
 *   connection BEFORE the commit — a flush failure rolls the whole
 *   command back;
 * - standalone callers flush themselves, inside their own transaction or
 *   in auto-commit mode (each operation then stands alone).
 *
 * Ordering & safety contract:
 * - operations run strictly FIFO on ONE connection, sequentially
 *   ({@see UnitOfWorkFlush}'s walk over {@see UnitOfWorkQueue}'s
 *   snapshot);
 * - the queue is cleared BEFORE execution starts: a failed operation is
 *   never retried from this queue (the surrounding transaction rollback
 *   is the failure story; a retried command re-records fresh operations);
 * - {@see flush()} re-entry is refused loudly — an operation that flushes
 *   mid-flush has ambiguous ordering and is a caller bug;
 * - recording DURING a flush is likewise refused for the same reason.
 */
final readonly class UnitOfWork
{
    private UnitOfWorkQueue $unitOfWorkQueue;

    /**
     * @param null|Closure(int): void $sleeper the wait seam: one callable
     *                                         receiving the backoff delay in milliseconds. Null keeps the
     *                                         engine's own microsecond sleep ({@see UnitOfWorkWait::block()}),
     *                                         the same seam the telemetry batch processor hides its wait
     *                                         behind — the suite observes the backoff without waiting it.
     */
    public function __construct(private ?Closure $sleeper = null)
    {
        $this->unitOfWorkQueue = new UnitOfWorkQueue();
    }

    /**
     * Queue a deferred operation; it receives the flush connection.
     *
     * @param callable(ConnectionInterface): void $operation
     */
    public function record(callable $operation): void
    {
        $this->unitOfWorkQueue->record($operation);
    }

    /**
     * Queue a single write as a prepared statement execution.
     */
    public function recordQuery(SqlQuery $sqlQuery): void
    {
        $this->unitOfWorkQueue->recordQuery($sqlQuery);
    }

    /**
     * Execute every queued operation FIFO on $connection and clear the
     * queue. Returns the number of operations executed.
     *
     * The queue is cleared BEFORE execution starts — a failed operation
     * is never retried from this queue. The surrounding transaction
     * rollback is the failure story; a retried command re-records fresh
     * operations.
     *
     * For opt-in retry of transient DB failures see
     * {@see flushRetrying()}.
     */
    public function flush(ConnectionInterface $connection): int
    {
        $operations = $this->unitOfWorkQueue->armFlush();
        if ($operations === null) {
            return 0;
        }

        try {
            return UnitOfWorkFlush::run($connection, $operations);
        } finally {
            $this->unitOfWorkQueue->disarm();
        }
    }

    /**
     * Flush with opt-in retry for transient DB failures.
     *
     * Unlike {@see flush()}, this method preserves the queued operations
     * across retry attempts — the snapshot is replayed each attempt
     * until either the flush succeeds or the policy is exhausted. The
     * queue is cleared ONLY on a successful flush; when a throwable
     * escapes this method (non-retryable, or the policy exhausted), the
     * queue is deliberately left populated for a potential replay by
     * the caller, and the caller's transaction rollback is the failure
     * story — the stale queue can never reach a commit path.
     *
     * Contract:
     * - Only the FLUSH phase is retried — never the command handler
     *   body. Side effects produced during dispatch are NOT re-run.
     * - The policy decides retryability via
     *   {@see UnitOfWorkRetryPolicy::isRetryable()}; non-retryable
     *   throwables propagate immediately.
     * - On exhaustion the last throwable propagates.
     * - The backoff wait between attempts reads the sleeper seam, so
     *   the wait the policy spells is observable without blocking the
     *   suite on it.
     *
     * @return int number of operations executed by the successful attempt
     */
    public function flushRetrying(
        ConnectionInterface $connection,
        UnitOfWorkRetryPolicy $unitOfWorkRetryPolicy,
    ): int {
        $operations = $this->unitOfWorkQueue->armReplay();
        if ($operations === null) {
            return 0;
        }

        try {
            $executed = UnitOfWorkReplay::run(
                $connection,
                $operations,
                $unitOfWorkRetryPolicy,
                $this->sleeper ?? UnitOfWorkWait::block(...),
            );
            // Success — drop the queue.
            $this->unitOfWorkQueue->clear();

            return $executed;
        } finally {
            $this->unitOfWorkQueue->disarm();
        }
    }

    /**
     * Number of operations waiting for the next flush.
     */
    public function pending(): int
    {
        return $this->unitOfWorkQueue->pending();
    }

    /**
     * Drop every queued operation without executing it (explicit abort).
     * Returns the number of operations discarded.
     */
    public function discard(): int
    {
        return $this->unitOfWorkQueue->discard();
    }
}
