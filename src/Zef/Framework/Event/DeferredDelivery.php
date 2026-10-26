<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * One pending delivery in the deferred arm's queue: the frozen
 * event, the single listener it is addressed to, the attempt
 * number the entry stands at, and the earliest moment a drain may
 * deliver it. Immutable by construction -- a retry is a NEW entry
 * ({@see withNextAttempt()}), so an entry's history is never
 * rewritten in place and the queue's order stays the only order
 * there is.
 */
final readonly class DeferredDelivery
{
    /**
     * @throws InvalidArgumentException when the attempt number is
     *                                  below one (a delivery that never tried is not a delivery)
     *                                  or the ready-at moment is negative (time is a plain
     *                                  non-negative integer here, the drain's own parameter)
     */
    public function __construct(
        private Event $event,
        private EventListener $eventListener,
        private int $attempt,
        private int $readyAt,
    ) {
        if ($attempt < 1) {
            throw new InvalidArgumentException(
                'A deferred delivery stands at an attempt number of at least'
                . ' one, got ' . $attempt . '.',
            );
        }

        if ($readyAt < 0) {
            throw new InvalidArgumentException(
                "A deferred delivery's ready-at moment cannot be negative,"
                . ' got ' . $readyAt . '.',
            );
        }
    }

    /**
     * The frozen event this entry delivers -- the payload was
     * validated at the defer boundary, before anything was queued.
     */
    public function event(): Event
    {
        return $this->event;
    }

    /**
     * The one listener this entry is addressed to -- the pool
     * snapshot at defer time, one entry per listener.
     */
    public function listener(): EventListener
    {
        return $this->eventListener;
    }

    /**
     * Which attempt this entry stands at, one-based: a fresh
     * admission stands at one, a retry at the next number.
     */
    public function attempt(): int
    {
        return $this->attempt;
    }

    /**
     * The earliest moment a drain may deliver this entry -- a
     * plain integer the drain is handed, never a clock read.
     */
    public function readyAt(): int
    {
        return $this->readyAt;
    }

    /**
     * The retried entry: the same event and listener, standing at
     * the next attempt number, due no earlier than the backoff
     * answered for the attempt that just failed.
     */
    public function withNextAttempt(int $attempt, int $readyAt): self
    {
        return new self($this->event, $this->eventListener, $attempt, $readyAt);
    }
}
