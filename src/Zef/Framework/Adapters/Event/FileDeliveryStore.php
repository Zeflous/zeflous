<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Override;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The deferred delivery store's first adapter: the local
 * filesystem's answer to the persistence port, and the seed of
 * the ROADMAP's Adapters lane -- one question owned by the Event
 * layer's port, one answer spelled here, the hexagonal direction
 * the whole tree's first port/adapter pair plants. Every record
 * is one owner-only JSON file named by the entry's own identity;
 * the dead-letter lane is a `dead/` subdirectory the operator
 * reads; the entry book is the store root itself.
 *
 * The protocol the port spells lands here as the file shapes it
 * takes: {@see takeDue()} reads the entry files and leaves them
 * (an entry taken but not settled is re-taken by the next pass --
 * the crash window that costs a redelivery, never a loss), every
 * drain answer is written before the spent entry is removed
 * (requeue and dead-letter both), and settling is the idempotent
 * unlink the at-least-once arithmetic assumes. The store is
 * single-drainer by declaration: two processes draining the same
 * directory concurrently will both take the same due entries --
 * the duplicate the idempotency doctrine owns, the exclusivity
 * the cross-process bus adapter's claim semantics are named to
 * answer. The folds the adapter composes: the admission write
 * ({@see DeliveryBatchWrite}), the due-selection scan
 * ({@see DeliveryDueScan}), the file layout ({@see DeliveryFiles}),
 * the dead-letter lane ({@see DeliveryDeadLane}), the write
 * discipline ({@see DeliveryStaging}) and the directory posture
 * ({@see DeliveryStoreDirectory}) -- with the regime's BOOKS and
 * JOURNAL spelled by their own lanes: the attribution lane
 * ({@see DeliveryAttributionLane}), the quarantine lane
 * ({@see DeliveryQuarantineLane}) and the drain journal
 * ({@see DeliveryDrainJournal}), each one owner-only record per
 * entry, identity or completed pass.
 */
final readonly class FileDeliveryStore implements DeferredDeliveryStore
{
    private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder;

    private DeliveryFiles $deliveryFiles;

    private DeliveryDueScan $deliveryDueScan;

    private DeliveryBatchWrite $deliveryBatchWrite;

    private DeliveryDeadLane $deliveryDeadLane;

    private DeliveryAttributionLane $deliveryAttributionLane;

    private DeliveryAttributionScan $deliveryAttributionScan;

    private DeliveryQuarantineLane $deliveryQuarantineLane;

    private DeliveryDrainJournal $deliveryDrainJournal;

    /**
     * @param string $directory the store's root directory (validated
     *                          by {@see DeliveryStoreDirectory}: existing, writable,
     *                          non-symlink, owner-only, outside the public document root)
     * @param int    $capacity  the pending bound (>= 1): the store
     *                          keeps at most this many pending entries
     *
     * @throws InvalidArgumentException when the capacity is below
     *                                  one, or the directory refuses
     */
    public function __construct(
        string $directory,
        int $capacity,
    ) {
        $resolvedDirectory = DeliveryStoreDirectory::resolve($directory);

        $this->deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $this->deliveryFiles = new DeliveryFiles($resolvedDirectory);
        $this->deliveryDueScan = new DeliveryDueScan(
            $this->deliveryFiles,
            new DeliveryEnvelopeDecoder(),
        );
        $this->deliveryBatchWrite = new DeliveryBatchWrite(
            $this->deliveryFiles,
            $this->deliveryEnvelopeEncoder,
            $capacity,
        );
        $this->deliveryDeadLane = new DeliveryDeadLane(
            $resolvedDirectory,
            new DeliveryStaging(),
        );
        $this->deliveryAttributionLane = new DeliveryAttributionLane(
            $resolvedDirectory,
            new DeliveryStaging(),
        );
        $this->deliveryAttributionScan = new DeliveryAttributionScan(
            $this->deliveryAttributionLane,
            new DeliveryAttributionDecoder(),
        );
        $this->deliveryQuarantineLane = new DeliveryQuarantineLane(
            $resolvedDirectory,
            new DeliveryStaging(),
        );
        $this->deliveryDrainJournal = new DeliveryDrainJournal(
            $resolvedDirectory,
            new DeliveryStaging(),
        );
    }

    #[Override]
    public function save(DeliveryEnvelope ...$envelopes): void
    {
        $this->deliveryBatchWrite->admit(...$envelopes);
    }

    #[Override]
    public function requeue(DeliveryEnvelope $deliveryEnvelope): void
    {
        $this->deliveryFiles->writeEntry(
            $deliveryEnvelope->entryId()->spelling(),
            $this->deliveryEnvelopeEncoder->encode($deliveryEnvelope),
        );
    }

    #[Override]
    public function takeDue(int $now): array
    {
        return $this->deliveryDueScan->takeDue($now);
    }

    #[Override]
    public function settle(DeliveryEnvelope ...$envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->deliveryFiles->removeEntry($envelope->entryId()->spelling());
        }
    }

    #[Override]
    public function deadLetter(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): void {
        $this->deliveryDeadLane->writeRecord(
            $deliveryEnvelope->entryId()->spelling(),
            $this->deliveryEnvelopeEncoder->encodeDeadLetter(
                $deliveryEnvelope,
                $failureClass,
                $failureMessage,
                $deadAt,
            ),
        );
    }

    #[Override]
    public function pendingCount(): int
    {
        return \count($this->deliveryFiles->entryFilenames());
    }

    #[Override]
    public function recordAttributedDeath(
        DeliveryEntryId $deliveryEntryId,
        ListenerIdentity $listenerIdentity,
        int $attributedAt,
    ): void {
        $this->deliveryAttributionLane->recordAttributedDeath(
            $deliveryEntryId->spelling(),
            $listenerIdentity->spelling(),
            $attributedAt,
        );
    }

    #[Override]
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        return $this->deliveryAttributionScan->deadLettersAttributedTo($listenerIdentity);
    }

    #[Override]
    public function quarantineListener(
        ListenerIdentity $listenerIdentity,
        int $quarantinedAt,
    ): void {
        $this->deliveryQuarantineLane->quarantineListener(
            $listenerIdentity->spelling(),
            $quarantinedAt,
        );
    }

    #[Override]
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return $this->deliveryQuarantineLane->isListenerQuarantined($listenerIdentity);
    }

    #[Override]
    public function recordDrainOutcome(DrainTrace $drainTrace): void
    {
        $this->deliveryDrainJournal->recordDrainOutcome($drainTrace);
    }
}
