<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;

/**
 * The PDO-backed delivery store's admission fold: the batch the port
 * refuses whole when it would push the store past its capacity, the
 * counts spelled -- the in-process queue's own doctrine, answered by
 * the store that can actually enforce it. The write itself rides
 * the entry-row fold's one multi-row INSERT, the engine's own
 * all-or-nothing: a batch the engine cannot hold whole (a row the
 * table already owns, a row the engine refuses) admits NOTHING, the
 * statement's own atomicity the mid-write compensation the Redis
 * answer spells by hand -- SQL never lands half a statement.
 */
final readonly class PdoDeliveryAdmission
{
    private IdentifierQuoting $identifierQuoting;

    /**
     * @param int $capacity the pending bound (>= 1): the store keeps
     *                      at most this many pending entries
     *
     * @throws InvalidArgumentException when the capacity is below one
     */
    public function __construct(
        private ConnectionInterface $connection,
        private PdoDeliveryTables $pdoDeliveryTables,
        private PdoDeliveryEntries $pdoDeliveryEntries,
        private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder,
        private int $capacity,
    ) {
        if ($this->capacity < 1) {
            throw new InvalidArgumentException(
                "A delivery store's capacity must be at least one, got "
                . $this->capacity . '.',
            );
        }

        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * Admits a batch of entries as one unit: the capacity read, the
     * refusal whole when the batch would cross the bound, the write
     * one statement the engine answers all-or-nothing.
     *
     * @param DeliveryEnvelope ...$envelopes the entries one durable defer produced
     *
     * @throws InvalidArgumentException when the batch would push the
     *                                  store past its capacity
     */
    public function admit(DeliveryEnvelope ...$envelopes): void
    {
        if ($envelopes === []) {
            return;
        }

        $demanded = \count($envelopes);
        $occupied = $this->pendingCount();

        if ($occupied + $demanded > $this->capacity) {
            throw new InvalidArgumentException(
                'The delivery store is full: capacity ' . $this->capacity
                . ', occupied ' . $occupied . ', demanded ' . $demanded
                . ' -- drain before deferring more.',
            );
        }

        $rows = [];

        foreach ($envelopes as $envelope) {
            $rows[] = [
                'entry_id' => $envelope->entryId()->spelling(),
                'record' => $this->deliveryEnvelopeEncoder->encode($envelope),
                'ready_at' => $envelope->readyAt(),
            ];
        }

        $this->pdoDeliveryEntries->writeRows($rows);
    }

    /**
     * How many entries the store still holds -- admitted,
     * not-yet-due, or taken-but-not-settled alike: the count the
     * capacity arithmetic reads, answered over the same table every
     * pending concern owns.
     *
     * @throws InvalidArgumentException when the engine refuses the
     *                                  count, or answers a shape the count contract does not
     *                                  spell
     */
    public function pendingCount(): int
    {
        $row = $this->connection->fetchOne(
            QueryBuilder::table(
                $this->pdoDeliveryTables->deliveryTable()->name,
                null,
                $this->identifierQuoting,
            )->select(new SqlExpression('COUNT(*) AS pending'))->build(),
        );

        $value = $row['pending'] ?? null;

        if (!\is_int($value) && !\is_string($value) && !\is_float($value)) {
            throw new InvalidArgumentException(
                'The pending count returned no aggregate value.',
            );
        }

        return (int) $value;
    }
}
