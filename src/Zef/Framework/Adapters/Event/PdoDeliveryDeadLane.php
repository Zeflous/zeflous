<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;

/**
 * The PDO-backed delivery store's dead lane: the operator's
 * dead-letter table, one row per spent entry keyed by the entry's
 * own identity, the SQL twin of the file answer's dead lane
 * ({@see DeliveryDeadLane}). The write rides the conflict-tail
 * seam's rewrite arm: the same entry dead-lettered twice (the
 * at-least-once duplicate) rewrites the same story, never doubles
 * the lane -- the latest death the record carries, the whole story
 * the Event lane's own encoder spells (the failure's class and
 * message, the dead-at moment, beside the entry's own record).
 */
final readonly class PdoDeliveryDeadLane
{
    public function __construct(
        private PdoDeliveryUpsert $pdoDeliveryUpsert,
        private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder,
        private PdoDeliveryTables $pdoDeliveryTables,
    ) {
    }

    /**
     * Records the spent entry's whole story where an operator can
     * read it.
     *
     * @throws \Zef\Framework\Database\DatabaseException when the
     *                                                   record cannot be written
     */
    public function deadLetter(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): void {
        $this->pdoDeliveryUpsert->insertRewrite(
            $this->pdoDeliveryTables->deadTable(),
            [
                'entry_id' => $deliveryEnvelope->entryId()->spelling(),
                'record' => $this->deliveryEnvelopeEncoder->encodeDeadLetter(
                    $deliveryEnvelope,
                    $failureClass,
                    $failureMessage,
                    $deadAt,
                ),
            ],
            'entry_id',
            'record',
        );
    }
}
