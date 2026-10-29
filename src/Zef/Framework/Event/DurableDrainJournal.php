<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * The durable drain's journal fold: the pass's own last act --
 * the five counts a completed pass answered with, at the now it
 * was drained at, journalled through the store and answered as
 * the outcome itself (KDD-EA12's seam -- the counts are the
 * traceability). A pass that did not complete writes no trace; a
 * completed pass's trace is the record an operator reads the
 * regime's history from, the same bytes either side of the store
 * choice.
 */
final readonly class DurableDrainJournal
{
    public function __construct(private DeferredDeliveryStore $deferredDeliveryStore)
    {
    }

    /**
     * Journals one completed pass: the outcome its counts spell,
     * the trace the store appends, the outcome itself answered
     * back -- the pass's return and its record are the same five
     * numbers, never two spellings of them.
     */
    public function journal(
        int $now,
        int $delivered,
        int $retried,
        int $deadLettered,
        int $quarantined,
    ): DrainOutcome {
        $drainOutcome = new DrainOutcome(
            $delivered,
            $retried,
            $deadLettered,
            $quarantined,
            $this->deferredDeliveryStore->pendingCount(),
        );
        $this->deferredDeliveryStore->recordDrainOutcome(DrainTrace::fromOutcome($now, $drainOutcome));

        return $drainOutcome;
    }
}
