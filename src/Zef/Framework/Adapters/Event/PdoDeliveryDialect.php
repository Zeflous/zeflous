<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Adapters\Database\ConnectionFields;
use Zef\Framework\Database\TableName;

/**
 * The PDO-backed delivery store's dialect fold: the spellings the
 * three drivers the DSN grammar accepts answer differently, keyed
 * on the connection's own driver word -- the database family's own
 * {@see \Zef\Framework\Adapters\Database\SchemaDialect} posture
 * replayed for this store's SQL. What the drivers share (the
 * queries the builder composes, the four books' DDL) never passes
 * through here; what they spell apart does: the delivery table's
 * DDL (MySQL carries the due-scan index inline because it is the
 * one of the three whose CREATE INDEX spell owns no IF NOT EXISTS
 * arm), the index statement the other two answer, and the
 * conflict-tail spellings the upsert seam appends (SQLite and
 * PostgreSQL speak the standard's ON CONFLICT, MySQL its own
 * ON DUPLICATE KEY UPDATE).
 *
 * The spellings for MySQL and PostgreSQL are pinned as strings in
 * this slice -- CI's socket reaches SQLite alone, the same recorded
 * posture the schema-reflection dialect carries for its two
 * information_schema dialects, with the live-socket fixture the
 * family's own recorded follow-up. SQLite's spellings are pinned
 * live, over the real in-memory engine the whole test battery
 * rides.
 */
final readonly class PdoDeliveryDialect
{
    public function __construct(private string $driver)
    {
        if (!\in_array($this->driver, ConnectionFields::DRIVERS, true)) {
            throw new InvalidArgumentException(
                'The PDO delivery store answers the three drivers the DSN'
                . " grammar accepts (mysql, pgsql, sqlite), got '{$this->driver}'.",
            );
        }
    }

    /**
     * The delivery table's DDL: the row the pending book owns -- the
     * entry's own identity the primary key, the record the Event
     * lane's encoder spells, the ready-at moment, and the two claim
     * columns the exclusivity fold writes. SQLite and PostgreSQL
     * share the spelling (the standard's types, the IF NOT EXISTS
     * arm both answer); MySQL carries the due-scan index inline
     * ({@see deliveryIndexDdl()} answers null for it) because its
     * CREATE INDEX spell owns no IF NOT EXISTS arm and a second
     * constructor on a fleet would race the first one's index into
     * a refused DDL.
     */
    public function deliveryTableDdl(TableName $tableName): string
    {
        $table = $tableName->name;

        if ($this->driver === 'mysql') {
            return 'CREATE TABLE IF NOT EXISTS ' . $table . ' ('
                . 'entry_id CHAR(32) NOT NULL PRIMARY KEY, '
                . 'record TEXT NOT NULL, '
                . 'ready_at BIGINT NOT NULL, '
                . 'claim_token CHAR(32) NULL, '
                . 'claim_expires_at BIGINT NULL, '
                . 'KEY ' . $table . '_ready_at (ready_at, entry_id)'
                . ')';
        }

        return 'CREATE TABLE IF NOT EXISTS ' . $table . ' ('
            . 'entry_id CHAR(32) NOT NULL PRIMARY KEY, '
            . 'record TEXT NOT NULL, '
            . 'ready_at BIGINT NOT NULL, '
            . 'claim_token CHAR(32) NULL, '
            . 'claim_expires_at BIGINT NULL'
            . ')';
    }

    /**
     * The due-scan index's DDL -- the index the file adapter's
     * honesty ledger names, answering the due selection without
     * reading every record, its (ready_at, entry_id) order the DU9
     * ordering doctrine for free. Null on MySQL: the table's own
     * DDL already carries the index inline, so no separate
     * statement exists to spell.
     */
    public function deliveryIndexDdl(TableName $tableName): ?string
    {
        if ($this->driver === 'mysql') {
            return null;
        }

        $table = $tableName->name;

        return 'CREATE INDEX IF NOT EXISTS ' . $table . '_ready_at ON ' . $table
            . ' (ready_at, entry_id)';
    }

    /**
     * The insert-once conflict tail: the idempotent join an already
     * present primary key turns into the no-op the doctrine assumes
     * -- the attribution book's per-entry row and the quarantine
     * book's membership. SQLite and PostgreSQL speak the standard's
     * ON CONFLICT DO NOTHING; MySQL its own no-op assignment under
     * ON DUPLICATE KEY UPDATE.
     */
    public function insertOnceTail(string $primaryKey): string
    {
        if ($this->driver === 'mysql') {
            return 'ON DUPLICATE KEY UPDATE ' . $primaryKey . ' = ' . $primaryKey;
        }

        return 'ON CONFLICT (' . $primaryKey . ') DO NOTHING';
    }

    /**
     * The insert-rewrite conflict tail: the upsert whose present
     * primary key means the SAME story rewritten, never doubled --
     * the dead lane's row, the latest death the record carries.
     * SQLite and PostgreSQL speak the standard's ON CONFLICT DO
     * UPDATE over the excluded row; MySQL its own VALUES() read
     * under ON DUPLICATE KEY UPDATE.
     */
    public function insertRewriteTail(string $primaryKey, string $column): string
    {
        if ($this->driver === 'mysql') {
            return 'ON DUPLICATE KEY UPDATE ' . $column . ' = VALUES(' . $column . ')';
        }

        return 'ON CONFLICT (' . $primaryKey . ') DO UPDATE SET ' . $column
            . ' = excluded.' . $column;
    }
}
