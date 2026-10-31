<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The INSERT statement's assembly fold: the validated row set spelled
 * as one `INSERT INTO ... (columns) VALUES (...)` statement with an
 * expression cell embedded verbatim and every bound cell a positional
 * placeholder.
 *
 * @internal
 */
final readonly class QueryInsertAssembly
{
    public function __construct(
        private SqlIdentifier $sqlIdentifier = new SqlIdentifier(),
    ) {
    }

    public function compile(QuerySnapshot $querySnapshot): SqlQuery
    {
        if ($querySnapshot->insertRows === []) {
            throw new QueryException('Insert query requires data (call insertRows() first).');
        }

        $columns = [];
        foreach (array_keys($querySnapshot->insertRows[0]) as $column) {
            $columns[] = $this->sqlIdentifier->quote($column, 'column');
        }

        $rowSql = [];

        /** @var list<null|bool|float|int|SqlExpression|string> $params the positional values every row spells */
        $params = [];
        foreach ($querySnapshot->insertRows as $insertRow) {
            $rowParams = [];

            foreach ($insertRow as $value) {
                if ($value instanceof SqlExpression) {
                    $rowParams[] = (string) $value;
                } else {
                    $rowParams[] = '?';
                    $params[] = $value;
                }
            }

            $rowSql[] = '(' . implode(', ', $rowParams) . ')';
        }

        $sql = 'INSERT INTO ' . $querySnapshot->table
            . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $rowSql);

        return new SqlQuery($sql, $params);
    }
}
