<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The column-list renderings the select side collects: the select
 * list (non-empty, each column through the {@see SqlColumn} spelling)
 * and the group-by list (likewise non-empty).
 *
 * @internal
 */
final class QueryColumns
{
    /**
     * @param array<SqlExpression|string> $columns
     *
     * @return list<string>
     */
    public static function select(SqlIdentifier $sqlIdentifier, array $columns, string $method): array
    {
        if ($columns === []) {
            throw new QueryException("{$method} requires at least one column.");
        }

        $rendered = [];
        foreach ($columns as $column) {
            $rendered[] = SqlColumn::render($sqlIdentifier, $column);
        }

        return $rendered;
    }

    /**
     * @param array<SqlExpression|string> $columns
     *
     * @return list<string>
     */
    public static function groupBy(SqlIdentifier $sqlIdentifier, array $columns): array
    {
        if ($columns === []) {
            throw new QueryException('groupBy() requires at least one column.');
        }

        $rendered = [];
        foreach ($columns as $column) {
            $rendered[] = SqlColumn::render($sqlIdentifier, $column);
        }

        return $rendered;
    }
}
