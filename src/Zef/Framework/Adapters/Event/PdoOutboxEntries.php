<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use JsonException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Event\OutboxMessage;

/**
 * The PDO-backed outbox store's entry-row fold: the outbox table's
 * own write and removal verbs -- every row the store holds lands
 * through here and leaves through here, so the row's shape (the
 * identity, the event's name, the payload the Event lane's JSON
 * spelling answers, the appended-at moment) is spelled once for
 * the append's batch, the relay's read and the relay's removal
 * alike.
 *
 * The batch write is ONE multi-row INSERT, the engine's own
 * all-or-nothing -- the delivery store's own admission doctrine
 * carried verbatim: a batch the engine cannot hold whole admits
 * NOTHING, the statement's own boundary, and SQL never lands half
 * a statement. The removal is ONE DELETE over the batch the relay
 * handed, in the read's own order carried -- the removal whose
 * absent row answers idempotently, the settled-is-forgotten
 * doctrine the delivery store's settle spells. The read and the
 * judgment it carries live in their own folds ({@see
 * PdoOutboxPending}, {@see PdoOutboxRecords}) -- the ceiling's own
 * fold doctrine, the delivery family's entries/pending/records
 * split carried.
 */
final readonly class PdoOutboxEntries
{
    private IdentifierQuoting $identifierQuoting;

    public function __construct(
        private ConnectionInterface $connection,
        private PdoOutboxTables $pdoOutboxTables,
    ) {
        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * Writes the pending rows one statement holds: the whole batch
     * admitted or refused together, the engine's own boundary --
     * riding whatever transaction the caller's scope carries
     * (never a scope of this fold's own: the same-transaction
     * write is the port's own doctrine).
     *
     * @param OutboxMessage ...$outboxMessages the rows one transactional
     *                                         defer (or one batch of them) produced
     *
     * @throws InvalidArgumentException when a row cannot be encoded
     *                                  -- unreachable by construction ({@see OutboxMessage}'s
     *                                  walk guarantees the survival), spelled because the
     *                                  encoding flags are a promise, not a hope
     */
    public function writeRows(OutboxMessage ...$outboxMessages): void
    {
        $rows = [];

        foreach ($outboxMessages as $outboxMessage) {
            $rows[] = [
                'message_id' => $outboxMessage->messageId()->spelling(),
                'event_name' => $outboxMessage->name()->spelling(),
                'payload' => $this->encodePayload($outboxMessage),
                'appended_at' => $outboxMessage->appendedAt(),
            ];
        }

        $this->connection->execute(
            QueryBuilder::table(
                $this->pdoOutboxTables->outboxTable()->name,
                null,
                $this->identifierQuoting,
            )->insertRows($rows)->build(),
        );
    }

    /**
     * Removes the relayed rows the pass handed, in the read's own
     * order carried -- the removal whose absent row answers
     * idempotently, the settled-is-forgotten doctrine the delivery
     * store's settle spells.
     *
     * @param list<OutboxMessage> $outboxMessages the relayed rows
     *
     * @throws InvalidArgumentException when the batch is empty, or
     *                                  the engine refuses the removal
     */
    public function removeRows(array $outboxMessages): void
    {
        if ($outboxMessages === []) {
            throw new InvalidArgumentException(
                'A relay pass removes the rows it relayed -- an empty removal'
                . ' is a pass that read nothing, and a pass that read nothing'
                . ' never reaches its own removal.',
            );
        }

        $messageIds = [];

        foreach ($outboxMessages as $outboxMessage) {
            $messageIds[] = $outboxMessage->messageId()->spelling();
        }

        $this->connection->execute(
            QueryBuilder::table(
                $this->pdoOutboxTables->outboxTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->delete()
                ->whereIn('message_id', $messageIds)
                ->build(),
        );
    }

    /**
     * Encodes one row's payload -- the Event lane's JSON spelling,
     * the same flags the delivery store's encoder carries (floats
     * keep their zero fraction).
     *
     * @throws InvalidArgumentException unreachable by construction,
     *                                  spelled because the encoding flags are a promise
     */
    private function encodePayload(OutboxMessage $outboxMessage): string
    {
        try {
            return json_encode(
                $outboxMessage->values(),
                \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(
                'The outbox row refused its payload spelling: '
                . $jsonException->getMessage(),
                0,
                $jsonException,
            );
        }
    }
}
