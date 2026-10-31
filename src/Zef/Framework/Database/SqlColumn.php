<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The column-spelling fold of the identifier grammar: the one place a
 * select-list, group-by or order-by column argument is rendered --
 * either a raw {@see SqlExpression} verbatim, or `col`, `table.col`
 * or `path AS alias` through the quoting grammar.
 *
 * @internal
 */
final class SqlColumn
{
    /**
     * Renders `col`, `table.col`, `path AS alias` or a raw expression.
     */
    public static function render(SqlIdentifier $sqlIdentifier, SqlExpression|string $column): string
    {
        if ($column instanceof SqlExpression) {
            return (string) $column;
        }

        $trimmed = mb_trim($column);
        if ($trimmed === '') {
            throw new QueryException('Column must not be empty.');
        }

        if (preg_match('/^([\w.]+)\s+AS\s+([A-Z_]\w{0,63})$/i', $trimmed, $m) === 1) {
            return $sqlIdentifier->columnPath($m[1]) . ' AS ' . $sqlIdentifier->quote($m[2], 'alias');
        }

        return $sqlIdentifier->columnPath($trimmed);
    }
}
