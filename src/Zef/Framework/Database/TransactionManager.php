<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Default {@see TransactionManagerInterface} implementation over a single
 * {@see ConnectionInterface}.
 *
 * The transaction mechanics themselves (BEGIN / SAVEPOINT nesting,
 * isolation, rollback-on-throw) are delegated to the connection primitive —
 * this class owns ONLY the managed-scope orchestration, every verb a
 * thin delegation over the scope state {@see TransactionHooks} owns:
 *
 * - the scope depth counts open managed scopes (raw connection
 *   transactions are invisible to it);
 * - the hooks accumulate FIFO across the whole nested scope tree;
 * - after the OUTERMOST managed commit, the drain's flushing mode makes
 *   hooks enqueued by hooks run inline instead of re-queueing; on
 *   outermost failure the queue is discarded.
 *
 * Instances are stateful and NOT shareable across concurrent dispatches
 * (PHP request scope) — wire one per bus/worker.
 *
 * The cooperative hook timeout guard: when $hookDurationThresholdMs is
 * set, each hook is timed and a debug log entry is emitted via $logger
 * when the threshold is exceeded. This is observability-only — PHP
 * cannot safely interrupt a running closure.
 */
final readonly class TransactionManager implements TransactionManagerInterface
{
    private TransactionHooks $transactionHooks;

    /**
     * @param null|int             $hookDurationThresholdMs soft threshold in milliseconds;
     *                                                      null disables the slow-hook debug log (default behaviour)
     * @param null|LoggerInterface $logger                  where to emit the debug
     *                                                      log; null stays silent by default
     */
    public function __construct(
        private ConnectionInterface $connection,
        ?int $hookDurationThresholdMs = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->transactionHooks = new TransactionHooks($hookDurationThresholdMs, $logger);
    }

    /**
     * @template T
     *
     * @param callable(ConnectionInterface): T $fn
     * @param null|IsolationLevel              $isolationLevel transaction isolation level for the scope
     *
     * @return T
     *
     * @throws Throwable The inner $fn threw, OR the underlying commit
     *                   primitive failed. The hook queue is discarded and the
     *                   connection rolled back. (Hook failures do NOT propagate
     *                   from here — they run only on a successful commit and throw
     *                   out of the drain.)
     */
    #[Override]
    public function withTransaction(callable $fn, ?IsolationLevel $isolationLevel = null): mixed
    {
        $outermost = $this->transactionHooks->enter();

        try {
            $result = $this->connection->transaction(
                static fn (ConnectionInterface $connection): mixed => $fn($connection),
                $isolationLevel,
            );
        } catch (Throwable $throwable) {
            $this->transactionHooks->failed($outermost);

            throw $throwable;
        }

        $this->transactionHooks->committed($outermost);

        return $result;
    }

    /**
     * Queue $hook to run after the current scope's OUTERMOST commit.
     *
     * Executes immediately when no managed scope is open (including
     * during hook flushing). The hook receives no arguments; capture
     * what it needs in the closure.
     *
     * @param callable(): void $hook
     *
     * @throws Throwable When invoked OUTSIDE a managed scope the hook
     *                   runs immediately and any throw propagates. When invoked
     *                   INSIDE a managed scope the hook is queued and may throw
     *                   later — during the drain, AFTER the commit has
     *                   been applied.
     */
    #[Override]
    public function afterCommit(callable $hook): void
    {
        if ($this->transactionHooks->flushing() || $this->transactionHooks->depth() === 0) {
            $hook();

            return;
        }

        $this->transactionHooks->queue($hook);
    }

    #[Override]
    public function inTransaction(): bool
    {
        return $this->transactionHooks->depth() > 0;
    }

    #[Override]
    public function level(): int
    {
        return $this->transactionHooks->depth();
    }
}
