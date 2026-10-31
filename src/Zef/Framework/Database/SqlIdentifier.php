<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The identifier grammar's quoting fold: validation and double-quoting
 * for every table and column name the builder accepts -- strict
 * `[A-Za-z_][A-Za-z0-9_]{0,63}`, at most one dot in a path -- so user
 * input can never become SQL syntax: every accepted identifier is
 * spelled back double-quoted, and anything dynamic travels as an
 * explicit {@see SqlExpression} instead (the greppable escape hatch).
 *
 * The column-list spellings (`path AS alias`, raw expressions) live in
 * their own fold, {@see SqlColumn}.
 */
final class SqlIdentifier
{
    private const string IDENT_RE = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/';

    private const int MAX_IDENTIFIER_BYTES = 64;

    /**
     * Validate and double-quote one identifier (max 64 bytes, strict grammar).
     */
    public function quote(string $identifier, string $context = 'identifier'): string
    {
        if ($identifier === '') {
            throw new QueryException("{$context} must not be empty.");
        }

        // The '8bit' encoding IS byte semantics (the BodyBudget
        // doctrine): the ceiling counts BYTES over an
        // ASCII-by-construction grammar, and the spelling is the one
        // the automated mb_str migration cannot silently widen.
        $bytes = mb_strlen($identifier, '8bit');
        if ($bytes > self::MAX_IDENTIFIER_BYTES) {
            throw new QueryException(
                "{$context} exceeds the " . self::MAX_IDENTIFIER_BYTES . "-byte limit (got {$bytes}).",
            );
        }

        if (preg_match(self::IDENT_RE, $identifier) !== 1) {
            $message = "Invalid {$context} '{$identifier}'.";
            // DX: make the SqlExpression escape hatch obvious when a raw
            // SQL fragment is mistakenly passed as an identifier -- keeps
            // raw SQL greppable instead of inviting unsafe workarounds.
            if (preg_match('/[\s()*,<>=]/', $identifier) === 1) {
                $message .= ' Identifiers must match [A-Za-z_][A-Za-z0-9_]{0,63}'
                    . ' (optionally table.column or column AS alias); for raw SQL pass an'
                    . ' explicit SqlExpression instead — e.g. new SqlExpression(...) as a'
                    . ' column argument, or SqlQuery::raw(...) for whole statements.';
            }

            throw new QueryException($message);
        }

        return '"' . $identifier . '"';
    }

    /**
     * Render a table name: an {@see SqlExpression} verbatim, else one or
     * two dot-separated segments, each validated and quoted.
     */
    public function table(SqlExpression|string $table): string
    {
        if ($table instanceof SqlExpression) {
            return (string) $table;
        }

        $segments = explode('.', $table);
        if (\count($segments) > 2) {
            throw new QueryException("Invalid table '{$table}' (at most one dot is allowed).");
        }

        $quoted = array_map(fn (string $s): string => $this->quote(mb_trim($s), 'table'), $segments);

        return implode('.', $quoted);
    }

    /**
     * Render a column path: `col` or `table.col`, each segment validated
     * and quoted.
     */
    public function columnPath(string $column): string
    {
        $segments = explode('.', $column);
        if (\count($segments) > 2) {
            throw new QueryException("Invalid column '{$column}' (at most one dot is allowed).");
        }

        $quoted = array_map(fn (string $s): string => $this->quote(mb_trim($s), 'column'), $segments);

        return implode('.', $quoted);
    }
}
