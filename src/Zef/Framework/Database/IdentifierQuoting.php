<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The identifier quoting vocabulary: the one driver dialect the
 * query family's SQL spelling carries -- how a validated identifier
 * is quoted and how the LIKE ESCAPE clause reads, the two
 * spellings where stock MySQL deviates from the SQL standard
 * (the audit finding of issue #778).
 *
 * `Ansi` is the SQL standard's spelling: double-quoted identifiers
 * and the LIKE escape character backslash with the clause
 * `ESCAPE '\'` -- the spelling PostgreSQL and SQLite answer as-is,
 * and the family's default, so every deployment that declares
 * neither driver word beyond the standard sees byte-identical SQL
 * to the pre-vocabulary builder.
 *
 * `MySql` is stock MySQL's spelling: backticks for identifiers, and
 * a LIKE escape clause no sql_mode can veto. The escape character
 * cannot stay the backslash there -- the clause's literal would need
 * the doubled `ESCAPE '\\'` spelling under the default sql_mode but
 * the bare one under `NO_BACKSLASH_ESCAPES`, two spellings the
 * builder cannot choose between without asking the server its mode
 * at build time -- so the escape character itself moves to `|`: a
 * character no LIKE grammar treats specially, spelled identically
 * under every sql_mode, and the pattern's own escaping rides the
 * same character (a literal `|` escaping to `||`, the wildcard
 * characters to `|%` and `|_`, a literal backslash to `|\`).
 */
enum IdentifierQuoting
{
    case Ansi;

    case MySql;

    /**
     * The vocabulary one driver word answers. MySQL is the one
     * mainstream engine whose stock sql_mode deviates from the
     * standard's quoting; every other word -- the remaining drivers
     * the DSN grammar accepts, and a custom ConnectionInterface
     * implementation's own word -- answers the standard's spelling,
     * which is the spelling SQL Server and Oracle answer too. The
     * word's own validation is not here: it lives where the driver
     * word is read ({@see \Zef\Framework\Adapters\Database\ConnectionFields::readDriver()}).
     */
    public static function forDriver(string $driver): self
    {
        return $driver === 'mysql' ? self::MySql : self::Ansi;
    }

    /**
     * Quote one validated identifier. The grammar's own vocabulary
     * (`[A-Za-z_][A-Za-z0-9_]{0,63}` -- {@see SqlIdentifier}'s
     * ceiling and grammar) contains neither quote character, so the
     * closing quote never needs doubling: a backtick or double quote
     * inside an identifier is a spelling the grammar refused before
     * it ever reached here.
     */
    public function quote(string $identifier): string
    {
        return match ($this) {
            self::MySql => '`' . $identifier . '`',
            self::Ansi => '"' . $identifier . '"',
        };
    }

    /**
     * The LIKE pattern's wildcard-escaped spelling, over this
     * vocabulary's escape character -- the escape character itself
     * escaped first (a literal `|` to `||` on MySQL's vocabulary, so
     * the later escapes' introducers are never re-escaped), then the
     * backslash, `%` and `_` each prefixed with it.
     */
    public function escapeLikePattern(string $pattern): string
    {
        return match ($this) {
            self::MySql => str_replace(['|', '\\', '%', '_'], ['||', '|\\', '|%', '|_'], $pattern),
            self::Ansi => str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $pattern),
        };
    }

    /**
     * The ESCAPE clause, leading space included, as this vocabulary's
     * driver reads it -- `ESCAPE '\'` on the standard's spelling,
     * `ESCAPE '|'` on MySQL's (the escape character's own reason is
     * the enum's docblock above).
     */
    public function likeEscapeClause(): string
    {
        return match ($this) {
            self::MySql => " ESCAPE '|'",
            self::Ansi => " ESCAPE '\\'",
        };
    }
}
