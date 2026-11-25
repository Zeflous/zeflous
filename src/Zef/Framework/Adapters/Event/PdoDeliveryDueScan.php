<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;

/**
 * The PDO-backed delivery store's due selection: the identities
 * whose ready-at has arrived at the drain's own now, in the index's
 * own order -- ready-at then entry identity, the DU9 ordering
 * doctrine the (ready_at, entry_id) index the schema fold ensures
 * carries for free -- the SQL twin of the file answer's due scan
 * ({@see DeliveryDueScan}). A READ, never a removal: the rows stay
 * until they are settled, which is what makes a crash between
 * taking and answering a redelivery instead of a loss.
 */
final readonly class PdoDeliveryDueScan
{
    private IdentifierQuoting $identifierQuoting;

    public function __construct(
        private ConnectionInterface $connection,
        private PdoDeliveryTables $pdoDeliveryTables,
    ) {
        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * The due entry identities, in the index's own order.
     *
     * @param int $now the drain's own now, the seconds the regime spells
     *
     * @return list<string> the due identities, ready-at then entry identity
     *
     * @throws InvalidArgumentException when the answer carries a
     *                                  cell that is not the entry identity's string -- the
     *                                  engine contract enforced rather than assumed
     */
    public function dueIdentities(int $now): array
    {
        $entryIdentities = [];

        foreach (
            $this->connection->fetchAll(
                QueryBuilder::table(
                    $this->pdoDeliveryTables->deliveryTable()->name,
                    null,
                    $this->identifierQuoting,
                )
                    ->select('entry_id')
                    ->where('ready_at', '<=', $now)
                    ->orderBy('ready_at')
                    ->orderBy('entry_id')
                    ->build(),
            ) as $row
        ) {
            $entryId = $row['entry_id'] ?? null;

            if (!\is_string($entryId)) {
                throw new InvalidArgumentException(
                    'The due selection refused its answer: an entry identity'
                    . ' cell is not the string the primary key spells.',
                );
            }

            $entryIdentities[] = $entryId;
        }

        return $entryIdentities;
    }
}
