<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

/**
 * One drain pass's answer, timestamped: the five counts that are the
 * deferred regime's whole observable outcome (KDD-EA12's seam --
 * "the counts are the seam", spelled there as the traceability
 * follow-up this record answers), at the drain's own now. The trace
 * is what the store's journal holds: an operator reads the regime's
 * history as one record per completed pass, the same five numbers
 * {@see DrainOutcome} answered with, plus the only "when" the
 * regime ever knew (KDD-EA4: time is a parameter, so the record
 * carries the drain's own now, never a clock's).
 *
 * A pass that did not complete writes no trace -- an interrupted
 * pass is a crash window the at-least-once protocol owns, and a
 * half-pass's counts would be a story the drain never answered
 * with.
 */
final readonly class DrainTrace
{
    private function __construct(
        private int $drainedAt,
        private int $delivered,
        private int $retried,
        private int $deadLettered,
        private int $quarantined,
        private int $pending,
    ) {
    }

    /**
     * The trace of one completed pass: the outcome it answered
     * with, at the now it was drained at.
     */
    public static function fromOutcome(int $drainedAt, DrainOutcome $drainOutcome): self
    {
        return new self(
            $drainedAt,
            $drainOutcome->delivered(),
            $drainOutcome->retried(),
            $drainOutcome->deadLettered(),
            $drainOutcome->quarantined(),
            $drainOutcome->pending(),
        );
    }

    /**
     * When the pass was drained -- the drain's own now.
     */
    public function drainedAt(): int
    {
        return $this->drainedAt;
    }

    /**
     * How many due entries the pass delivered.
     */
    public function delivered(): int
    {
        return $this->delivered;
    }

    /**
     * How many due entries the pass requeued as retries.
     */
    public function retried(): int
    {
        return $this->retried;
    }

    /**
     * How many due entries spent their last permitted attempt and
     * failed -- the letters the store's dead lane gained.
     */
    public function deadLettered(): int
    {
        return $this->deadLettered;
    }

    /**
     * How many listeners the pass newly quarantined -- the
     * containment's own count, earned this pass.
     */
    public function quarantined(): int
    {
        return $this->quarantined;
    }

    /**
     * How many entries the store still held after the pass.
     */
    public function pending(): int
    {
        return $this->pending;
    }
}
