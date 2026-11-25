<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\DatabaseException;
use Zef\Framework\Database\SqlQuery;

/**
 * The PDO-backed delivery store's schema fold: the layout posture
 * the file answer spells as its directory tree and the Redis answer
 * never needs (its keys materialise on use), answered for SQL as
 * the tables the store owns ensured present before the first verb
 * runs -- every CREATE carries its own IF NOT EXISTS arm, so the
 * second store over the same schema is the no-op the first one's
 * tables already answered, and a schema the connection cannot
 * ensure is the honest halt the construction refuses to talk past
 * (the boot-time-connection doctrine the Redis composition arm
 * spelled: a declared store without its tables is a refusal at the
 * wiring, never a first-drain catch-all the operator waited for a
 * worker to meet).
 *
 * The delivery table and its due-scan index ride the dialect fold
 * (the one place the three drivers spell apart); the dead lane, the
 * two books and the journal are the four DDL spellings every driver
 * shares, so they live here -- one row shape per port concern, the
 * same lanes the file answer owns as directories.
 */
final readonly class PdoDeliverySchema
{
    private const string CREATE_TABLE_IF_NOT_EXISTS = 'CREATE TABLE IF NOT EXISTS ';

    public function __construct(
        private ConnectionInterface $connection,
        private PdoDeliveryDialect $pdoDeliveryDialect,
        private PdoDeliveryTables $pdoDeliveryTables,
    ) {
    }

    /**
     * Ensures the five tables and the due-scan index exist, creating
     * the ones the schema does not hold yet.
     *
     * @throws DatabaseException when the engine refuses a DDL
     *                           statement -- the honest halt, the
     *                           store's tables named by the statement
     *                           the engine refused
     */
    public function ensure(): void
    {
        $statements = [
            $this->pdoDeliveryDialect->deliveryTableDdl(
                $this->pdoDeliveryTables->deliveryTable(),
            ),
            $this->deadTableDdl(),
            $this->attributedTableDdl(),
            $this->quarantineTableDdl(),
            $this->journalTableDdl(),
        ];

        $deliveryIndexDdl = $this->pdoDeliveryDialect->deliveryIndexDdl(
            $this->pdoDeliveryTables->deliveryTable(),
        );

        if ($deliveryIndexDdl !== null) {
            $statements[] = $deliveryIndexDdl;
        }

        foreach ($statements as $statement) {
            $this->connection->execute(SqlQuery::raw($statement));
        }
    }

    /**
     * The dead lane's DDL: one row per dead-lettered entry, keyed by
     * the entry's own identity, the record the Event lane's encoder
     * spells -- the same story rewritten is the same row replaced.
     */
    private function deadTableDdl(): string
    {
        return self::CREATE_TABLE_IF_NOT_EXISTS . $this->pdoDeliveryTables->deadTable()->name
            . ' (entry_id CHAR(32) NOT NULL PRIMARY KEY, record TEXT NOT NULL)';
    }

    /**
     * The attribution book's DDL: one row per attributed entry, the
     * listener's identity the row carries.
     */
    private function attributedTableDdl(): string
    {
        return self::CREATE_TABLE_IF_NOT_EXISTS
            . $this->pdoDeliveryTables->attributedTable()->name
            . ' (entry_id CHAR(32) NOT NULL PRIMARY KEY,'
            . ' listener_identity VARCHAR(64) NOT NULL)';
    }

    /**
     * The quarantine book's DDL: one row per contained identity --
     * the membership IS the containment.
     */
    private function quarantineTableDdl(): string
    {
        return self::CREATE_TABLE_IF_NOT_EXISTS
            . $this->pdoDeliveryTables->quarantineTable()->name
            . ' (listener_identity VARCHAR(64) NOT NULL PRIMARY KEY)';
    }

    /**
     * The drain journal's DDL: one row per completed pass, the
     * record's name a freshly minted entry-style identity, the
     * record the Event lane's trace encoder spells.
     */
    private function journalTableDdl(): string
    {
        return self::CREATE_TABLE_IF_NOT_EXISTS . $this->pdoDeliveryTables->journalTable()->name
            . ' (record_id CHAR(32) NOT NULL PRIMARY KEY, record TEXT NOT NULL)';
    }
}
