<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * What one drain pass answered with: the five counts that are the
 * deferred regime's whole observable outcome. Delivered (succeeded
 * this pass), retried (failed and requeued with a backoff),
 * dead-lettered (failed for the last permitted attempt),
 * quarantined (listeners whose attributed dead letters reached the
 * threshold THIS pass), and pending (entries the book still holds
 * after the pass -- not-yet-due and requeued alike).
 *
 * The counts are the traceability seam (KDD-EA12): an
 * observability fold will one day timestamp exactly these numbers,
 * the way {@see DispatchOutcome}'s delivered-to count is ED9's
 * seam.
 */
final readonly class DrainOutcome
{
    public function __construct(
        private int $delivered,
        private int $retried,
        private int $deadLettered,
        private int $quarantined,
        private int $pending,
    ) {
    }

    /**
     * How many due entries succeeded this pass.
     */
    public function delivered(): int
    {
        return $this->delivered;
    }

    /**
     * How many due entries failed and were requeued as retries
     * with a future ready-at.
     */
    public function retried(): int
    {
        return $this->retried;
    }

    /**
     * How many due entries spent their last permitted attempt and
     * failed -- the letters the dead-letter book gained.
     */
    public function deadLettered(): int
    {
        return $this->deadLettered;
    }

    /**
     * How many listeners were quarantined this pass -- the
     * poison-pill containment's own count, newly earned.
     */
    public function quarantined(): int
    {
        return $this->quarantined;
    }

    /**
     * How many entries the book still holds after the pass.
     */
    public function pending(): int
    {
        return $this->pending;
    }
}
