<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Adapters\Database\ConnectionFields;
use Zef\Framework\Database\TableName;

/**
 * The PDO-backed outbox store's dialect fold: the spellings the
 * three drivers the DSN grammar accepts answer differently, keyed
 * on the connection's own driver word -- the delivery store's own
 * dialect posture ({@see PdoDeliveryDialect}) replayed for this
 * store's SQL. What the drivers share (the queries the builder
 * composes, the table's own column list) never passes through
 * here; what they spell apart does: the outbox table's DDL (MySQL
 * carries the read-order index inline because it is the one of
 * the three whose CREATE INDEX spell owns no IF NOT EXISTS arm)
 * and the index statement the other two answer.
 *
 * The spellings for MySQL and PostgreSQL are pinned as strings in
 * this slice -- CI's socket reaches SQLite alone, the same recorded
 * posture the delivery store's dialect carries, with the
 * live-socket fixture the family's own recorded follow-up.
 * SQLite's spellings are pinned live, over the real in-memory
 * engine the whole test battery rides.
 */
final readonly class PdoOutboxDialect
{
    public function __construct(private string $driver)
    {
        if (!\in_array($this->driver, ConnectionFields::DRIVERS, true)) {
            throw new InvalidArgumentException(
                'The PDO outbox store answers the three drivers the DSN'
                . " grammar accepts (mysql, pgsql, sqlite), got '{$this->driver}'.",
            );
        }
    }

    /**
     * The outbox table's DDL: the row the pending set owns -- the
     * row's own identity the primary key, the event's name, the
     * payload the Event lane's JSON spelling answers, and the
     * appended-at moment the relay's read order keys on. SQLite
     * and PostgreSQL share the spelling (the standard's types, the
     * IF NOT EXISTS arm both answer); MySQL carries the read-order
     * index inline ({@see outboxIndexDdl()} answers null for it)
     * because its CREATE INDEX spell owns no IF NOT EXISTS arm
     * and a second constructor on a fleet would race the first
     * one's index into a refused DDL.
     */
    public function outboxTableDdl(TableName $tableName): string
    {
        $table = $tableName->name;

        if ($this->driver === 'mysql') {
            return 'CREATE TABLE IF NOT EXISTS ' . $table . ' ('
                . 'message_id CHAR(32) NOT NULL PRIMARY KEY, '
                . 'event_name VARCHAR(64) NOT NULL, '
                . 'payload TEXT NOT NULL, '
                . 'appended_at BIGINT NOT NULL, '
                . 'KEY ' . $table . '_appended_at (appended_at, message_id)'
                . ')';
        }

        return 'CREATE TABLE IF NOT EXISTS ' . $table . ' ('
            . 'message_id CHAR(32) NOT NULL PRIMARY KEY, '
            . 'event_name VARCHAR(64) NOT NULL, '
            . 'payload TEXT NOT NULL, '
            . 'appended_at BIGINT NOT NULL'
            . ')';
    }

    /**
     * The read-order index's DDL -- the (appended_at, message_id)
     * order the relay's pending read keys on, answering the read
     * without sorting every pending row, the DU9 ordering doctrine
     * carried from the delivery store's own due-scan index. Null
     * on MySQL: the table's own DDL already carries the index
     * inline, so no separate statement exists to spell.
     */
    public function outboxIndexDdl(TableName $tableName): ?string
    {
        if ($this->driver === 'mysql') {
            return null;
        }

        $table = $tableName->name;

        return 'CREATE INDEX IF NOT EXISTS ' . $table . '_appended_at ON '
            . $table . ' (appended_at, message_id)';
    }
}
