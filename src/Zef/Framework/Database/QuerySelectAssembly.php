<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The SELECT statement's assembly fold: the one place the select side's
 * collected parts become a {@see SqlQuery} -- columns, DISTINCT, table
 * and alias, joins, the where suffix, grouping, having, ordering and
 * paging, each clause appended only when it carries content.
 *
 * @internal
 */
final readonly class QuerySelectAssembly
{
    public function __construct(
        private SqlIdentifier $sqlIdentifier = new SqlIdentifier(),
    ) {
    }

    public function compile(QuerySnapshot $querySnapshot, QueryWhere $queryWhere): SqlQuery
    {
        if ($querySnapshot->table === null) {
            throw new QueryException('Select query requires a table (call QueryBuilder::table() first).');
        }

        $columns = $querySnapshot->columns === [] ? ['*'] : $querySnapshot->columns;
        $sql = 'SELECT ' . ($querySnapshot->distinct ? 'DISTINCT ' : '') . implode(', ', $columns)
            . ' FROM ' . $querySnapshot->table;
        if ($querySnapshot->tableAlias !== null) {
            $sql .= ' AS ' . $this->sqlIdentifier->quote($querySnapshot->tableAlias, 'table alias');
        }

        foreach ($querySnapshot->joins as $join) {
            $sql .= ' ' . $join;
        }

        $sql .= $queryWhere->suffix();
        if ($querySnapshot->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $querySnapshot->groupBy);
        }

        $sql .= $querySnapshot->having->suffix();
        if ($querySnapshot->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $querySnapshot->orderBy);
        }

        if ($querySnapshot->limit !== null) {
            $sql .= ' LIMIT ' . $querySnapshot->limit;
        }

        if ($querySnapshot->offset !== null) {
            $sql .= ' OFFSET ' . $querySnapshot->offset;
        }

        return new SqlQuery($sql, [...$queryWhere->params(), ...$querySnapshot->having->params()]);
    }
}
