<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The file-backed delivery store's attribution scan: the count the
 * quarantine threshold reads -- one walk over the attribution
 * lane's records, each read and decoded fail-closed through
 * {@see DeliveryAttributionDecoder}, the identities counted by the
 * spelling the listener's own grammar answers. The mirror of the
 * entry book's {@see DeliveryDueScan}, over the books' own lane.
 *
 * The scan owns the QUESTION (how many deaths one identity is
 * attributed); the lane owns WHERE the records live; the decoder
 * owns WHAT they spell -- three questions, three seams, the
 * family's own geometry.
 */
final readonly class DeliveryAttributionScan
{
    public function __construct(
        private DeliveryAttributionLane $deliveryAttributionLane,
        private DeliveryAttributionDecoder $deliveryAttributionDecoder,
    ) {
    }

    /**
     * How many attributed deaths the lane holds for the listener's
     * identity -- the count the quarantine threshold reads.
     *
     * @throws InvalidArgumentException when the lane cannot be
     *                                  scanned or a record refuses to decode
     */
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        $attributed = 0;

        foreach ($this->deliveryAttributionLane->attributionFilenames() as $filename) {
            $identity = $this->deliveryAttributionDecoder->decodedListenerIdentity(
                $this->deliveryAttributionLane->readAttribution($filename),
                $filename,
            );

            if ($identity !== $listenerIdentity->spelling()) {
                continue;
            }

            ++$attributed;
        }

        return $attributed;
    }
}
