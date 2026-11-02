<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The unit's queue: the deferred operations, the mid-flush recording
 * refusal, and the two arm spellings the flush phases walk — the plain
 * flush's take-and-clear, and the retrying replay's preserve-until-
 * success. The flushing flag the re-entry guards read is the queue's
 * own state.
 *
 * @internal
 */
final class UnitOfWorkQueue
{
    /** @var list<callable(ConnectionInterface): void> */
    private array $operations = [];

    private bool $flushing = false;

    /**
     * Queue a deferred operation.
     *
     * @param callable(ConnectionInterface): void $operation
     */
    public function record(callable $operation): void
    {
        if ($this->flushing) {
            throw new TransactionException('Cannot record operations while the UnitOfWork is flushing.');
        }

        $this->operations[] = $operation;
    }

    /**
     * Queue a single write as a prepared statement execution.
     */
    public function recordQuery(SqlQuery $sqlQuery): void
    {
        $this->record(static function (ConnectionInterface $connection) use ($sqlQuery): void {
            $connection->execute($sqlQuery);
        });
    }

    /**
     * Arms the plain flush: re-entry refused loudly, the queue cleared
     * BEFORE execution starts (a failed operation is never retried from
     * this queue), the snapshot answered. Null when there is nothing
     * to run.
     *
     * @return null|list<callable(ConnectionInterface): void>
     */
    public function armFlush(): ?array
    {
        if ($this->flushing) {
            throw new TransactionException('UnitOfWork::flush() re-entered while flushing.');
        }

        if ($this->operations === []) {
            return null;
        }

        $operations = $this->operations;
        $this->operations = [];

        $this->flushing = true;

        return $operations;
    }

    /**
     * Arms the retrying replay: re-entry refused loudly, the queue
     * PRESERVED (the snapshot is replayed per attempt and cleared only
     * on the successful attempt), the snapshot answered. Null when
     * there is nothing to run.
     *
     * @return null|list<callable(ConnectionInterface): void>
     */
    public function armReplay(): ?array
    {
        if ($this->flushing) {
            throw new TransactionException('UnitOfWork::flushRetrying() re-entered while flushing.');
        }

        if ($this->operations === []) {
            return null;
        }

        $this->flushing = true;

        return $this->operations;
    }

    /**
     * Disarms the flush phase (the finally every flush spelling walks).
     */
    public function disarm(): void
    {
        $this->flushing = false;
    }

    /**
     * Drops the queue (the retrying flush's success, and the explicit
     * abort).
     */
    public function clear(): void
    {
        $this->operations = [];
    }

    /**
     * Number of operations waiting for the next flush.
     */
    public function pending(): int
    {
        return \count($this->operations);
    }

    /**
     * Drops every queued operation without executing it (explicit
     * abort). Answers the number of operations discarded.
     */
    public function discard(): int
    {
        $discarded = \count($this->operations);
        $this->operations = [];

        return $discarded;
    }
}
