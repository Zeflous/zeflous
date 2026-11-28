<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;

/**
 * The PDO-backed outbox store's pending selection: the raw rows the
 * relay's pass reads, in the read order -- appended-at then row
 * identity, the DU9 ordering doctrine the index the schema fold
 * ensures carries for free -- at most the limit the pass spelled.
 * The SQL twin of the delivery store's due selection ({@see
 * PdoDeliveryDueScan}): a READ, never a removal -- the rows stay
 * until the pass's own removal takes them, which is what makes a
 * crashed pass's rollback a re-read instead of a loss.
 *
 * The cells this fold answers are RAW -- the judgment (the engine
 * contract each cell must spell, the payload's fail-closed decode)
 * lives in the records fold ({@see PdoOutboxRecords}), so the two
 * questions never share a seam: WHERE the rows are read is this
 * fold's, WHAT each row says is the records' own.
 */
final readonly class PdoOutboxPending
{
    private IdentifierQuoting $identifierQuoting;

    public function __construct(
        private ConnectionInterface $connection,
        private PdoOutboxTables $pdoOutboxTables,
    ) {
        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * The raw pending rows, in the read order.
     *
     * @param int $limit the most rows one pass reads (>= 1)
     *
     * @return list<array<string, mixed>> the raw rows the engine answered
     *
     * @throws InvalidArgumentException when the limit is below one
     */
    public function pendingRows(int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'A relay pass relays at least one row, got ' . $limit . '.',
            );
        }

        return $this->connection->fetchAll(
            QueryBuilder::table(
                $this->pdoOutboxTables->outboxTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->select('message_id', 'event_name', 'payload', 'appended_at')
                ->orderBy('appended_at')
                ->orderBy('message_id')
                ->limit($limit)
                ->build(),
        );
    }
}
