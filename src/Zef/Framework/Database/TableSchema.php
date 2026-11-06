<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * One reflected table: the grammar-validated name plus the three
 * answers the question owns -- the columns in the driver's own order,
 * the indexes as answered, the foreign keys as answered.
 *
 * The value refuses a table whose column list answers the same name
 * twice: a driver that answers one column under two rows is a driver
 * answering a shape no table spelled, and the worst failure shape
 * available is the silent one -- a map-keyed fold would quietly keep
 * the last answer and the consumer would never learn the socket lied.
 */
final readonly class TableSchema
{
    /**
     * @param list<ColumnSchema>     $columns     the columns, in the driver's answer order
     * @param list<IndexSchema>      $indexes     the indexes, in the driver's answer order
     * @param list<ForeignKeySchema> $foreignKeys the foreign keys, in the driver's answer order
     *
     * @throws QueryException when the same column name answers twice
     */
    public function __construct(
        public TableName $name,
        public array $columns,
        public array $indexes,
        public array $foreignKeys,
    ) {
        $answered = [];

        foreach ($columns as $column) {
            if (\in_array($column->name, $answered, true)) {
                throw new QueryException(
                    "The reflected table '{$name->name}' answers the column '{$column->name}' twice.",
                );
            }

            $answered[] = $column->name;
        }
    }
}
