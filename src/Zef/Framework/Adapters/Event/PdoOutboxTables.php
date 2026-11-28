<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\TableName;

/**
 * The PDO-backed outbox store's table vocabulary: the one table
 * one prefix hangs, the SQL twin of the delivery store's own
 * five-table family ({@see PdoDeliveryTables}) -- the store's
 * whole namespace spelled as the value object the database family
 * already owns ({@see TableName}, whose grammar refuses every
 * composed name that could ever become SQL syntax), so the DDL
 * fold and every query fold address the same validated name and
 * the prefix is judged once, at the composition the whole store
 * rides.
 *
 * The one table is the port's one holding concern: the pending
 * outbox rows ({@see outboxTable()}) -- the row's own identity the
 * primary key, the event's name, the payload the Event lane's
 * JSON spelling answers, and the appended-at moment the relay's
 * read order keys on. The relayed row is not a second table's
 * concern: relayed is REMOVED, the same settled-is-forgotten
 * doctrine the delivery store's settle spells, so the table is
 * the pending set alone and never grows without bound.
 */
final readonly class PdoOutboxTables
{
    private TableName $tableName;

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
                "A PDO outbox store's table prefix must not be empty -- the"
                . " un-prefixed vocabulary is the host schema's own, never the"
                . " store's to claim.",
            );
        }

        $this->tableName = $this->composedTableName($prefix . 'outbox');
    }

    /**
     * The pending outbox rows: one row per transactional defer, the
     * row's own identity the primary key, the event's name and
     * payload, and the appended-at moment the relay's read order
     * keys on -- every row the table holds is pending, because
     * relayed is removed, never marked.
     */
    public function outboxTable(): TableName
    {
        return $this->tableName;
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
                "A PDO outbox store's table prefix must compose valid table"
                . ' names (letters, digits and underscores, a letter or an'
                . " underscore first, at most sixty-four bytes): '{$name}'"
                . ' refuses.',
                0,
                $queryException,
            );
        }
    }
}
