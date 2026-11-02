<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The managed scope's own state: the scope depth the manager's verbs
 * count, and the after-commit hook queue — the FIFO accumulation across
 * the nested scope tree, the clear-before-drain discipline, and the
 * inline-while-flushing mode hooks registered during the drain answer
 * through ({@see HookDrain} owns the drain walk itself, with its
 * cooperative slow-hook guard).
 *
 * @internal
 */
final class TransactionHooks
{
    /** @var list<callable(): void> */
    private array $hooks = [];

    private bool $flushing = false;

    private int $scopeDepth = 0;

    /**
     * @param null|int             $hookDurationThresholdMs the slow-hook threshold
     *                                                      the drain times each hook against; null disables the guard
     * @param null|LoggerInterface $logger                  where the slow-hook debug
     *                                                      entry is emitted; null stays silent
     */
    public function __construct(
        private readonly ?int $hookDurationThresholdMs = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Opens a managed scope (the depth the nesting verbs count) and
     * answers whether the scope just opened is the OUTERMOST one.
     */
    public function enter(): bool
    {
        ++$this->scopeDepth;

        return $this->scopeDepth === 1;
    }

    /**
     * Closes a FAILED scope: the depth unwinds, and an outermost failure
     * discards every queued hook — a failed command never emits its
     * events, and never replays them on a retry.
     */
    public function failed(bool $outermost): void
    {
        --$this->scopeDepth;
        if (!$outermost) {
            return;
        }

        $this->hooks = [];
    }

    /**
     * Closes a SUCCEEDED scope: the depth unwinds, and the outermost
     * commit drains the hook queue.
     */
    public function committed(bool $outermost): void
    {
        --$this->scopeDepth;
        if (!$outermost) {
            return;
        }

        $this->drain();
    }

    /**
     * Queue $hook for the current scope tree's outermost commit.
     *
     * @param callable(): void $hook
     */
    public function queue(callable $hook): void
    {
        $this->hooks[] = $hook;
    }

    /**
     * True while the queue is being drained — the mode that makes hooks
     * registered during the drain run inline instead of re-queueing.
     */
    public function flushing(): bool
    {
        return $this->flushing;
    }

    /**
     * Nesting depth of the open managed scopes: 0 = none (raw
     * connection transactions do not count — scheduling follows
     * managed scopes only).
     */
    public function depth(): int
    {
        return $this->scopeDepth;
    }

    /**
     * Drain the queue in FIFO order after the outermost commit.
     *
     * The queue is cleared BEFORE draining so a hook failure can never
     * replay already-executed hooks on a later scope; the flushing mode
     * makes hooks registered during the drain run inline.
     *
     * @throws Throwable A queued hook threw. The throw propagates to the
     *                   caller AFTER the commit has succeeded. The remaining hooks
     *                   in the queue are NOT executed (the queue was cleared
     *                   before draining started).
     */
    private function drain(): void
    {
        $pending = $this->hooks;
        $this->hooks = [];
        if ($pending === []) {
            return;
        }

        $this->flushing = true;

        try {
            HookDrain::run($pending, $this->hookDurationThresholdMs, $this->logger);
        } finally {
            $this->flushing = false;
        }
    }
}
