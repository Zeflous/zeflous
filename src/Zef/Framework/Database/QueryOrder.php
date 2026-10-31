<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The order-by clause's rendering fold: the two-direction vocabulary
 * and the `column DIR` spelling over the column grammar.
 *
 * @internal
 */
final class QueryOrder
{
    /**
     * Renders `column ASC|DESC`, the direction validated and upper-cased.
     */
    public static function render(SqlIdentifier $sqlIdentifier, SqlExpression|string $column, string $direction): string
    {
        $dir = mb_strtoupper($direction);
        if (!\in_array($dir, ['ASC', 'DESC'], true)) {
            throw new QueryException("Unknown order direction '{$direction}' (allowed: ASC, DESC).");
        }

        return SqlColumn::render($sqlIdentifier, $column) . ' ' . $dir;
    }
}
