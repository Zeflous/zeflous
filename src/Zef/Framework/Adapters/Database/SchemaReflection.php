<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IndexSchema;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableName;
use Zef\Framework\Database\TableSchema;

/**
 * The reflection's orchestrating fold: the SERVICE over the
 * connection the wiring's `database.schema` identity composes -- the
 * Doctrine schema-manager split arrived at the tree's own seams. The
 * port is never widened: every question the family asks travels
 * through `ConnectionInterface`'s own fetchAll verb, the dialect's
 * spellings answer through {@see SchemaDialect}, and the answered
 * rows fold into the schema value objects through the family's own
 * row folds ({@see SchemaColumns}, {@see SchemaIndexes},
 * {@see SchemaForeignKeys}) -- one delegation each, the
 * {@see PdoConnection} lineage over the read side.
 *
 * The miss refuses loudly: a table whose columns question answers
 * empty is a name the driver does not know, and the silent shape --
 * a TableSchema that answers nothing while the consumer believes it
 * reflected something -- is the worst failure shape available (the
 * connections map's own doctrine, spelled one fold over).
 *
 * @api
 */
final readonly class SchemaReflection
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $driver,
    ) {
    }

    /**
     * The tables the live socket answers, in the driver's own name
     * order, as the grammar fold's own values.
     *
     * @return list<TableName>
     *
     * @throws QueryException when any answered cell refuses the narrowing
     */
    public function tables(): array
    {
        $tables = [];

        foreach ($this->ask(SchemaDialect::tables($this->driver)) as $row) {
            $tables[] = new TableName(SchemaCells::string($row, 'name'));
        }

        return $tables;
    }

    /**
     * One table's whole reflected shape: the columns, the indexes and
     * the foreign keys, each in the driver's answer order.
     *
     * @throws QueryException when the columns question answers empty (the reflected
     *                        name owns no table) or any answered cell refuses
     */
    public function table(TableName $tableName): TableSchema
    {
        $columns = SchemaColumns::fold(
            $this->driver,
            $this->ask(SchemaDialect::columns($this->driver, $tableName)),
        );

        if ($columns === []) {
            throw new QueryException(
                "The driver answered no columns for table '{$tableName->name}'"
                . ' -- the reflected name owns no table.',
            );
        }

        return new TableSchema(
            $tableName,
            $columns,
            $this->indexes($tableName),
            SchemaForeignKeys::fold(
                $this->driver,
                $this->ask(SchemaDialect::foreignKeys($this->driver, $tableName)),
            ),
        );
    }

    /**
     * The indexes question's orchestration: the dialect's rows plus
     * the per-index column-name seam the SQLite fold walks -- the one
     * nested ask the family owns, handed to the fold as a closure so
     * the fold itself stays a pure row fold.
     *
     * @return list<IndexSchema>
     */
    private function indexes(TableName $tableName): array
    {
        return SchemaIndexes::fold(
            $this->driver,
            $this->ask(SchemaDialect::indexes($this->driver, $tableName)),
            fn (string $indexName): array => $this->indexColumnNames($indexName),
        );
    }

    /**
     * The nested ask: one index's covered column names, in the
     * PRAGMA's own order.
     *
     * @return list<string>
     */
    private function indexColumnNames(string $indexName): array
    {
        $names = [];

        foreach ($this->ask(SchemaDialect::indexColumns('sqlite', $indexName)) as $row) {
            $names[] = SchemaCells::string($row, 'name');
        }

        return $names;
    }

    /**
     * One question asked through the port's own fetch verb.
     *
     * @return list<array<string, mixed>>
     */
    private function ask(SqlQuery $sqlQuery): array
    {
        return $this->connection->fetchAll($sqlQuery);
    }
}
