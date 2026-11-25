<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\TableName;

/**
 * The PDO-backed delivery store's table vocabulary: the five tables
 * one prefix hangs, the SQL twin of the Redis answer's key family
 * ({@see RedisDeliveryKeys}) -- the store's whole namespace spelled
 * as the value object the database family already owns
 * ({@see TableName}, whose grammar refuses every composed name that
 * could ever become SQL syntax), so the DDL fold and every query
 * fold address the same validated names and the prefix is judged
 * once, at the composition the whole store rides.
 *
 * The five tables are the port's five concerns, one each: the
 * pending delivery rows ({@see deliveryTable()}, the record and the
 * claim columns one row owns), the operator's dead lane
 * ({@see deadTable()}), the attribution book
 * ({@see attributedTable()}), the quarantine book
 * ({@see quarantineTable()}) and the drain journal
 * ({@see journalTable()}) -- the same lanes the file answer spells
 * as directories and the Redis answer as key families.
 */
final readonly class PdoDeliveryTables
{
    private TableName $deliveryTable;

    private TableName $deadTable;

    private TableName $attributedTable;

    private TableName $quarantineTable;

    private TableName $journalTable;

    /**
     * @param string $prefix the store's table namespace: the
     *                       deployment's own spelling every table
     *                       name starts with, so one schema can hold
     *                       more than one store (or leave the
     *                       framework's tables visibly the
     *                       framework's)
     *
     * @throws InvalidArgumentException when the prefix is empty, or
     *                                  a composed table name refuses
     *                                  the identifier grammar the
     *                                  database family owns (letters,
     *                                  digits and underscores, a
     *                                  letter or an underscore first,
     *                                  at most sixty-four bytes)
     */
    public function __construct(string $prefix)
    {
        if ($prefix === '') {
            throw new InvalidArgumentException(
                "A PDO delivery store's table prefix must not be empty -- the"
                . " un-prefixed vocabulary is the host schema's own, never the"
                . " store's to claim.",
            );
        }

        $this->deliveryTable = $this->composedTableName($prefix . 'delivery');
        $this->deadTable = $this->composedTableName($prefix . 'delivery_dead');
        $this->attributedTable = $this->composedTableName($prefix . 'delivery_attributed');
        $this->quarantineTable = $this->composedTableName($prefix . 'delivery_quarantine');
        $this->journalTable = $this->composedTableName($prefix . 'delivery_journal');
    }

    /**
     * The pending delivery rows: one row per admitted or requeued
     * entry, the entry's own identity the primary key, the record
     * the Event lane's encoder spells, the ready-at moment and the
     * two claim columns the exclusivity fold owns.
     */
    public function deliveryTable(): TableName
    {
        return $this->deliveryTable;
    }

    /**
     * The operator's dead lane: one row per dead-lettered entry,
     * keyed by the entry's own identity -- the same story rewritten
     * is the same row replaced, never a doubling.
     */
    public function deadTable(): TableName
    {
        return $this->deadTable;
    }

    /**
     * The attribution book: one row per attributed entry, the
     * listener's identity the row carries -- the count the
     * quarantine threshold reads is a WHERE over this table.
     */
    public function attributedTable(): TableName
    {
        return $this->attributedTable;
    }

    /**
     * The quarantine book: one row per contained identity -- the
     * membership IS the containment, exactly as the Redis answer's
     * set and the file answer's lane spell it.
     */
    public function quarantineTable(): TableName
    {
        return $this->quarantineTable;
    }

    /**
     * The drain journal: one row per completed pass, the record's
     * name a freshly minted entry-style identity so two passes at
     * the same now are two rows, never one overwritten.
     */
    public function journalTable(): TableName
    {
        return $this->journalTable;
    }

    /**
     * Validates one composed table name through the database
     * family's own grammar, refusing in the port's declared family
     * ({@see InvalidArgumentException}, the spelling every adapter
     * over this port answers) with the offending name spelled and
     * the grammar's own refusal chained -- the grammar stays the
     * family's single spelling, the family stays the port's.
     *
     * @throws InvalidArgumentException when the composed name refuses
     *                                  the identifier grammar
     */
    private function composedTableName(string $name): TableName
    {
        try {
            return new TableName($name);
        } catch (QueryException $queryException) {
            throw new InvalidArgumentException(
                "A PDO delivery store's table prefix must compose valid table"
                . ' names (letters, digits and underscores, a letter or an'
                . " underscore first, at most sixty-four bytes): '{$name}'"
                . ' refuses.',
                0,
                $queryException,
            );
        }
    }
}
