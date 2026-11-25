<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Override;
use Random\RandomException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The deferred delivery store's database answer: the persistence
 * port answered over the database family's own connection engine
 * -- the third store the port's docblock names (the file, Redis and
 * PDO answers), the record spelling the Event lane's encoder
 * already owns (the record is the same JSON either side of the
 * store choice). The connection every fold rides passes through
 * the port boundary fold first ({@see PdoDeliveryBoundary}), so
 * the engine's own typed refusals arrive as the port's declared
 * family on every verb alike; the due selection reads the index
 * the schema fold ensures; the exclusivity the file adapter
 * declared absent is the two claim columns the delivery row owns
 * -- ONE conditional UPDATE per due entry whose WHERE is the whole
 * claim contract (unclaimed, this drainer's own, or a visibility
 * timeout handed on), the engine itself the arbiter, no second
 * statement ever needed; the crash window a lost claim costs is
 * the redelivery the at-least-once doctrine already owns.
 *
 * The crash windows are the row's own atomicity: settle is the
 * entry-row fold's DELETE the settled row's absence answers
 * idempotently; requeue is its INSERT under the retry's fresh
 * identity; the admission is one multi-row INSERT the engine
 * answers all-or-nothing (the mid-write compensation the Redis
 * answer spells by hand is SQL's own statement boundary); the dead
 * lane, the books and the journal ride their own folds.
 */
final readonly class PdoDeliveryStore implements DeferredDeliveryStore
{
    private PdoDeliveryEntries $pdoDeliveryEntries;

    private PdoDeliveryAdmission $pdoDeliveryAdmission;

    private PdoDeliveryClaim $pdoDeliveryClaim;

    private PdoDeliveryRecords $pdoDeliveryRecords;

    private PdoDeliveryDeadLane $pdoDeliveryDeadLane;

    private PdoDeliveryBooks $pdoDeliveryBooks;

    private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder;

    /**
     * @param ConnectionInterface $connection          the database family's own port -- one
     *                                                 connection, one lifetime, the reconnect
     *                                                 policy the deployment's own glue owns
     *                                                 (the Redis answer's own posture, carried)
     * @param int                 $capacity            the pending bound (>= 1): the store
     *                                                 keeps at most this many pending entries
     * @param int                 $claimTimeoutSeconds the visibility timeout (>= 1) a
     *                                                 crashed drainer's claims survive for
     * @param string              $prefix              the store's table namespace: the
     *                                                 deployment's own spelling every table
     *                                                 name starts with
     *
     * @throws InvalidArgumentException when the capacity or the
     *                                  visibility timeout is below one, the prefix refuses
     *                                  the identifier grammar, the connection's driver word
     *                                  owns no dialect, or the store's tables cannot be
     *                                  ensured -- the honest halt, the store's tables named
     *                                  by the statement the engine refused (the
     *                                  boot-time-connection doctrine the Redis composition
     *                                  arm spelled)
     * @throws RandomException          when the engine's random source
     *                                  refuses to answer the drainer's token
     */
    public function __construct(
        ConnectionInterface $connection,
        int $capacity,
        int $claimTimeoutSeconds,
        string $prefix,
    ) {
        $pdoDeliveryBoundary = new PdoDeliveryBoundary($connection);
        $pdoDeliveryTables = new PdoDeliveryTables($prefix);
        $pdoDeliveryDialect = new PdoDeliveryDialect($pdoDeliveryBoundary->driver());
        $pdoDeliveryUpsert = new PdoDeliveryUpsert($pdoDeliveryBoundary, $pdoDeliveryDialect);
        $this->deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $this->pdoDeliveryEntries = new PdoDeliveryEntries($pdoDeliveryBoundary, $pdoDeliveryTables);
        $this->pdoDeliveryAdmission = new PdoDeliveryAdmission(
            $pdoDeliveryBoundary,
            $pdoDeliveryTables,
            $this->pdoDeliveryEntries,
            $this->deliveryEnvelopeEncoder,
            $capacity,
        );
        $this->pdoDeliveryClaim = new PdoDeliveryClaim(
            $pdoDeliveryBoundary,
            $pdoDeliveryTables,
            new PdoDeliveryDueScan($pdoDeliveryBoundary, $pdoDeliveryTables),
            DeliveryEntryId::mint()->spelling(),
            $claimTimeoutSeconds,
        );
        $this->pdoDeliveryRecords = new PdoDeliveryRecords(
            $this->pdoDeliveryEntries,
            new DeliveryEnvelopeDecoder(),
        );
        $this->pdoDeliveryDeadLane = new PdoDeliveryDeadLane(
            $pdoDeliveryUpsert,
            $this->deliveryEnvelopeEncoder,
            $pdoDeliveryTables,
        );
        $this->pdoDeliveryBooks = new PdoDeliveryBooks(
            $pdoDeliveryBoundary,
            $pdoDeliveryTables,
            $pdoDeliveryUpsert,
        );
        new PdoDeliverySchema(
            $pdoDeliveryBoundary,
            $pdoDeliveryDialect,
            $pdoDeliveryTables,
        )->ensure();
    }

    #[Override]
    public function save(DeliveryEnvelope ...$envelopes): void
    {
        $this->pdoDeliveryAdmission->admit(...$envelopes);
    }

    #[Override]
    public function requeue(DeliveryEnvelope $deliveryEnvelope): void
    {
        $this->pdoDeliveryEntries->writeRows([[
            'entry_id' => $deliveryEnvelope->entryId()->spelling(),
            'record' => $this->deliveryEnvelopeEncoder->encode($deliveryEnvelope),
            'ready_at' => $deliveryEnvelope->readyAt(),
        ]]);
    }

    #[Override]
    public function takeDue(int $now): array
    {
        return $this->pdoDeliveryRecords->answeredDeliveries(
            $this->pdoDeliveryClaim->claimDue($now),
        );
    }

    #[Override]
    public function settle(DeliveryEnvelope ...$envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->pdoDeliveryEntries->removeEntry($envelope->entryId()->spelling());
        }
    }

    #[Override]
    public function deadLetter(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): void {
        $this->pdoDeliveryDeadLane->deadLetter(
            $deliveryEnvelope,
            $failureClass,
            $failureMessage,
            $deadAt,
        );
    }

    #[Override]
    public function pendingCount(): int
    {
        return $this->pdoDeliveryAdmission->pendingCount();
    }

    #[Override]
    public function recordAttributedDeath(
        DeliveryEntryId $deliveryEntryId,
        ListenerIdentity $listenerIdentity,
        int $attributedAt,
    ): void {
        $this->pdoDeliveryBooks->recordAttributedDeath($deliveryEntryId, $listenerIdentity);
    }

    #[Override]
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        return $this->pdoDeliveryBooks->deadLettersAttributedTo($listenerIdentity);
    }

    #[Override]
    public function quarantineListener(
        ListenerIdentity $listenerIdentity,
        int $quarantinedAt,
    ): void {
        $this->pdoDeliveryBooks->quarantineListener($listenerIdentity);
    }

    #[Override]
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return $this->pdoDeliveryBooks->isListenerQuarantined($listenerIdentity);
    }

    #[Override]
    public function recordDrainOutcome(DrainTrace $drainTrace): void
    {
        $this->pdoDeliveryBooks->recordDrainOutcome($drainTrace);
    }
}
