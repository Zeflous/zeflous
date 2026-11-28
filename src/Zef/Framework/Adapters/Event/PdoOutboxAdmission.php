<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Event\OutboxMessage;

/**
 * The PDO-backed outbox store's admission fold: the batch the
 * port refuses whole when it would push the outbox past its
 * capacity, the counts spelled -- the delivery store's own
 * admission doctrine ({@see PdoDeliveryAdmission}) carried to the
 * write side the transactional lane owns. The write itself rides
 * the entry-row fold's one multi-row INSERT, the engine's own
 * all-or-nothing, and the append NEVER opens a scope of its own:
 * the row rides the transaction the caller's scope carries over
 * the same connection, which is the same-transaction write the
 * whole lane is named for -- the business write and the outbox
 * row land together or neither lands.
 *
 * The count the capacity arithmetic reads is this fold's own ({@see
 * pendingCount()}), answered over the same table every pending
 * concern owns -- the admission's own question, the delivery
 * family's own division.
 */
final readonly class PdoOutboxAdmission
{
    private IdentifierQuoting $identifierQuoting;

    /**
     * @param int $capacity the pending bound (>= 1): the outbox
     *                      keeps at most this many un-relayed rows
     *
     * @throws InvalidArgumentException when the capacity is below one
     */
    public function __construct(
        private ConnectionInterface $connection,
        private PdoOutboxTables $pdoOutboxTables,
        private PdoOutboxEntries $pdoOutboxEntries,
        private int $capacity,
    ) {
        if ($this->capacity < 1) {
            throw new InvalidArgumentException(
                "An outbox store's capacity must be at least one, got "
                . $this->capacity . '.',
            );
        }

        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * Admits a batch of rows as one unit: the capacity read, the
     * refusal whole when the batch would cross the bound, the
     * write one statement the engine answers all-or-nothing --
     * riding the caller's own scope, never a scope of this fold's
     * own.
     *
     * @param OutboxMessage ...$outboxMessages the rows the host's
     *                                         transaction produced
     *
     * @throws InvalidArgumentException when the batch would push the
     *                                  outbox past its capacity
     */
    public function admit(OutboxMessage ...$outboxMessages): void
    {
        if ($outboxMessages === []) {
            return;
        }

        $demanded = \count($outboxMessages);
        $occupied = $this->pendingCount();

        if ($occupied + $demanded > $this->capacity) {
            throw new InvalidArgumentException(
                'The outbox store is full: capacity ' . $this->capacity
                . ', occupied ' . $occupied . ', demanded ' . $demanded
                . ' -- relay before deferring more.',
            );
        }

        $this->pdoOutboxEntries->writeRows(...$outboxMessages);
    }

    /**
     * How many rows the outbox still holds -- every appended row
     * the relay has not removed yet -- the count the capacity
     * arithmetic reads, answered over the same table every pending
     * concern owns.
     *
     * @throws InvalidArgumentException when the engine refuses the
     *                                  count, or answers a shape the count contract does not
     *                                  spell
     */
    public function pendingCount(): int
    {
        $row = $this->connection->fetchOne(
            QueryBuilder::table(
                $this->pdoOutboxTables->outboxTable()->name,
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
