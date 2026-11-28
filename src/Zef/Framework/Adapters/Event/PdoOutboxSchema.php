<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\DatabaseException;
use Zef\Framework\Database\SqlQuery;

/**
 * The PDO-backed outbox store's schema fold: the layout posture
 * the delivery store's schema spells for its five tables, answered
 * for the outbox's one -- the table ensured present before the
 * first verb runs, every CREATE carrying its own IF NOT EXISTS
 * arm, so the second store over the same schema is the no-op the
 * first one's table already answered, and a schema the connection
 * cannot ensure is the honest halt the construction refuses to
 * talk past (the boot-time-connection doctrine the delivery
 * store's composition arm spelled: a declared outbox without its
 * table is a refusal at the wiring, never a first-relay catch-all
 * the operator waited for a worker to meet).
 */
final readonly class PdoOutboxSchema
{
    public function __construct(
        private ConnectionInterface $connection,
        private PdoOutboxDialect $pdoOutboxDialect,
        private PdoOutboxTables $pdoOutboxTables,
    ) {
    }

    /**
     * Ensures the outbox table and its read-order index exist,
     * creating the ones the schema does not hold yet.
     *
     * @throws DatabaseException when the engine refuses a DDL
     *                           statement -- the honest halt, the
     *                           store's table named by the statement
     *                           the engine refused
     */
    public function ensure(): void
    {
        $statements = [
            $this->pdoOutboxDialect->outboxTableDdl(
                $this->pdoOutboxTables->outboxTable(),
            ),
        ];

        $outboxIndexDdl = $this->pdoOutboxDialect->outboxIndexDdl(
            $this->pdoOutboxTables->outboxTable(),
        );

        if ($outboxIndexDdl !== null) {
            $statements[] = $outboxIndexDdl;
        }

        foreach ($statements as $statement) {
            $this->connection->execute(SqlQuery::raw($statement));
        }
    }
}
