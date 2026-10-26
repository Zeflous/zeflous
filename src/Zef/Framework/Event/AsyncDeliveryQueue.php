<?php

declare(strict_types=1);

namespace Zef\Framework\Event;

use InvalidArgumentException;

/**
 * The deferred arm's bounded book of pending deliveries. The book
 * keeps two promises: an admission is atomic -- a batch either
 * enters whole or is refused whole, so a defer never strands a
 * partial delivery -- and the entry order is the delivery order
 * among due entries, the queue's own FIFO.
 *
 * Time is a read parameter, never a clock: {@see takeDue()}
 * answers the entries whose ready-at has arrived at the "now" the
 * caller spells, removes exactly those, and leaves the
 * not-yet-due in their order. Requeued retries join the tail with
 * their future ready-at (KDD-EA9): a drain is one pass over the
 * due, and a base delay of zero is due at the NEXT drain, never
 * re-delivered within the pass that failed it.
 */
final class AsyncDeliveryQueue
{
    /** @var list<DeferredDelivery> */
    private array $pending = [];

    /**
     * @throws InvalidArgumentException when the capacity is below
     *                                  one -- a queue that can hold nothing is not a queue
     */
    public function __construct(private readonly int $capacity)
    {
        if ($capacity < 1) {
            throw new InvalidArgumentException(
                "A delivery queue's capacity must be at least one, got "
                . $capacity . '.',
            );
        }
    }

    /**
     * Admits a batch of entries as one unit, appending them in
     * pool order. The capacity refusal is the backpressure the
     * boundary owns (KDD-EA8): the whole batch is refused with the
     * counts spelled, and draining is the host's job.
     *
     * @param list<DeferredDelivery> $deliveries the entries one defer produced
     *
     * @throws InvalidArgumentException when the batch would push
     *                                  the book past its capacity
     */
    public function admit(array $deliveries): void
    {
        $demanded = \count($deliveries);

        if (\count($this->pending) + $demanded > $this->capacity) {
            throw new InvalidArgumentException(
                'The delivery queue is full: capacity ' . $this->capacity
                . ', occupied ' . \count($this->pending) . ', demanded '
                . $demanded . ' -- drain before deferring more.',
            );
        }

        foreach ($deliveries as $delivery) {
            $this->pending[] = $delivery;
        }
    }

    /**
     * The entries whose ready-at has arrived at the given now, in
     * queue order, removed from the book -- the not-yet-due stay
     * behind in their order. The caller (the drain fold) owns what
     * happens to them: delivered, or requeued as a retry.
     *
     * @return list<DeferredDelivery>
     */
    public function takeDue(int $now): array
    {
        $due = [];
        $remaining = [];

        foreach ($this->pending as $delivery) {
            if ($delivery->readyAt() <= $now) {
                $due[] = $delivery;
            } else {
                $remaining[] = $delivery;
            }
        }

        $this->pending = $remaining;

        return $due;
    }

    /**
     * Requeues a retried entry at the tail. A requeue is bounded
     * by the pass that took the entry -- it can never push the
     * book past the count that pass started from, so the capacity
     * refusal stays an admission-time concern alone.
     */
    public function requeue(DeferredDelivery $deferredDelivery): void
    {
        $this->pending[] = $deferredDelivery;
    }

    /**
     * How many entries the book holds -- admitted, awaiting their
     * ready-at, or requeued as retries.
     */
    public function count(): int
    {
        return \count($this->pending);
    }
}
