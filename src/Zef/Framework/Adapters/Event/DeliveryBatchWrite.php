<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Throwable;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;

/**
 * The file-backed delivery store's admission write fold: how a
 * batch of envelopes becomes pending entry files -- the capacity
 * backpressure with the counts spelled (the whole batch or none,
 * the in-process arm's own doctrine), the per-entry writes over
 * the layout fold, and the compensation that removes what a
 * refused mid-write batch already wrote, so the store answers a
 * refused admission unchanged. An entry that cannot be removed
 * strands: the refusal names the count, and the stranded entries
 * stay pending (they are real work -- the next drain delivers
 * them).
 */
final readonly class DeliveryBatchWrite
{
    /**
     * @throws InvalidArgumentException when the capacity is below
     *                                  one -- a store that can hold nothing is not a store
     */
    public function __construct(
        private DeliveryFiles $deliveryFiles,
        private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder,
        private int $capacity,
    ) {
        if ($this->capacity < 1) {
            throw new InvalidArgumentException(
                "A delivery store's capacity must be at least one, got "
                . $this->capacity . '.',
            );
        }
    }

    /**
     * Admits a batch of entries as one unit.
     *
     * @param DeliveryEnvelope ...$envelopes the entries one durable defer produced
     *
     * @throws InvalidArgumentException when the batch would push the
     *                                  store past its capacity, or the store cannot hold it whole
     */
    public function admit(DeliveryEnvelope ...$envelopes): void
    {
        $demanded = \count($envelopes);
        $occupied = \count($this->deliveryFiles->entryFilenames());

        if ($occupied + $demanded > $this->capacity) {
            throw new InvalidArgumentException(
                'The delivery store is full: capacity ' . $this->capacity
                . ', occupied ' . $occupied . ', demanded ' . $demanded
                . ' -- drain before deferring more.',
            );
        }

        $written = [];

        try {
            foreach ($envelopes as $envelope) {
                $this->deliveryFiles->writeEntry(
                    $envelope->entryId()->spelling(),
                    $this->deliveryEnvelopeEncoder->encode($envelope),
                );
                $written[] = $envelope;
            }
        } catch (Throwable $throwable) {
            $this->compensate($written);

            throw $throwable;
        }
    }

    /**
     * Removes the entries a refused batch already wrote.
     *
     * @param list<DeliveryEnvelope> $written the entries the refused batch wrote
     */
    private function compensate(array $written): void
    {
        $stranded = 0;

        foreach ($written as $envelope) {
            try {
                $this->deliveryFiles->removeEntry($envelope->entryId()->spelling());
            } catch (Throwable) {
                ++$stranded;
            }
        }

        if ($stranded > 0) {
            throw new InvalidArgumentException(
                'The refused durable delivery batch could not be compensated'
                . ' whole: ' . $stranded . ' written entries stay in the store'
                . ' and will drain -- remove them deliberately, or let the'
                . ' drain answer them.',
            );
        }
    }
}
