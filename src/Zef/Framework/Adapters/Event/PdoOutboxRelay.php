<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Event\OutboxMessage;

/**
 * The PDO-backed outbox store's relay fold: one relay pass as ONE
 * unit over the store's own connection -- the pending rows read,
 * the handoff's writes and the relayed rows' removal
 * both-or-neither, the exactly-once handoff into the durable arm
 * the port's doctrine spells (KDD-DU1's deferred answer, landed):
 * a refused handoff (the delivery store's capacity backpressure, a
 * shape the live definitions no longer answer) rolls the whole
 * pass back and every row stays pending, so the next pass
 * re-reads them; a crash mid-pass costs the same rollback the
 * engine's own statement boundary answers. The handoff's writes
 * land wherever the connection carries them -- the composition
 * demand the port's docblock spells: the outbox and the delivery
 * store the relay feeds ride the SAME connection the host spells,
 * which is what makes the read, the defers and the removals one
 * unit.
 *
 * The transaction is the ENGINE's own managed scope
 * ({@see ConnectionInterface::transaction()}): rolled back on any
 * throw, the throwable propagating untouched -- the port's
 * declared family answers at the boundary the handoff's refusals
 * already spell, and the engine's own refusals arrive translated
 * by the boundary fold the whole store rides. The read's two
 * halves live in their own folds ({@see PdoOutboxPending} the raw
 * rows, {@see PdoOutboxRecords} the judgment) and the removal is
 * the entry-row fold's own verb.
 */
final readonly class PdoOutboxRelay
{
    public function __construct(
        private ConnectionInterface $connection,
        private PdoOutboxPending $pdoOutboxPending,
        private PdoOutboxRecords $pdoOutboxRecords,
        private PdoOutboxEntries $pdoOutboxEntries,
    ) {
    }

    /**
     * One relay pass: reads at most the limit's pending rows in
     * the read order, hands them to the handoff inside the pass's
     * own transaction, and removes every handed row when the
     * handoff answered.
     *
     * @param int                               $limit   the most rows one pass relays (>= 1)
     * @param callable(OutboxMessage ...): void $handoff the fold that writes each row's
     *                                                   delivery entries into the delivery store over
     *                                                   the same connection
     *
     * @return int how many rows the pass relayed
     *
     * @throws InvalidArgumentException when the limit is below one,
     *                                  or any verb the pass rides is refused -- the whole
     *                                  pass rolled back, every row still pending
     */
    public function relay(int $limit, callable $handoff): int
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'A relay pass relays at least one row, got ' . $limit . '.',
            );
        }

        $outboxMessages = $this->connection->transaction(
            function () use ($limit, $handoff): array {
                $outboxMessages = $this->pdoOutboxRecords->answeredMessages(
                    $this->pdoOutboxPending->pendingRows($limit),
                );

                if ($outboxMessages === []) {
                    return [];
                }

                $handoff(...$outboxMessages);

                $this->pdoOutboxEntries->removeRows($outboxMessages);

                return $outboxMessages;
            },
        );

        return \count($outboxMessages);
    }
}
