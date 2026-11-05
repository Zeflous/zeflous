<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlIdentifier;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableName;

/**
 * The schema-reflection dialect fold: the per-driver SQL the
 * reflection's questions travel as -- the {@see ConnectionDsn}
 * lineage one question over, answering the three drivers the DSN
 * grammar already accepts. SQLite answers through its PRAGMA family;
 * MySQL and PostgreSQL through their `information_schema` spellings,
 * every selected cell aliased to the canonical key its row fold
 * reads: the dialect owns BOTH the spell and the answer's key
 * vocabulary, so no fold downstream branches on a driver's native
 * column names.
 *
 * The two `information_schema` dialects are spell-only in this slice:
 * CI's socket reaches SQLite alone, so their spells are pinned as
 * strings and their row folds over the documented shapes, with the
 * live-socket fixture posture the design record names carried as the
 * family's own recorded follow-up.
 *
 * @internal
 */
final class SchemaDialect
{
    /**
     * The tables question's spell: the deployment's own tables, in
     * the driver's name order -- SQLite's internal `sqlite_*` tables
     * and both dialects' views (a named non-goal) excluded by the
     * spell itself.
     *
     * @throws ConnectionException when the driver owns no dialect
     */
    public static function tables(string $driver): SqlQuery
    {
        return SqlQuery::raw(match ($driver) {
            'sqlite' => 'SELECT "name" AS name FROM sqlite_master'
                . " WHERE type = 'table' AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\'"
                . ' ORDER BY "name"',
            'mysql' => 'SELECT TABLE_NAME AS name FROM information_schema.TABLES'
                . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
                . ' ORDER BY TABLE_NAME',
            'pgsql' => 'SELECT TABLE_NAME AS name FROM information_schema.TABLES'
                . " WHERE TABLE_SCHEMA = current_schema() AND TABLE_TYPE = 'BASE TABLE'"
                . ' ORDER BY TABLE_NAME',
            default => throw new ConnectionException("Unsupported database driver '{$driver}'."),
        });
    }

    /**
     * The columns question's spell for one table: the canonical keys
     * (`name`, `declared_type`, `is_nullable`, `dflt_value`,
     * `pk_position`) the columns fold reads, with the primary key's
     * own ordinal answered by the constraint join the two
     * `information_schema` dialects spell.
     *
     * @throws ConnectionException when the driver owns no dialect
     */
    public static function columns(string $driver, TableName $tableName): SqlQuery
    {
        return SqlQuery::raw(match ($driver) {
            'sqlite' => "PRAGMA table_info('" . $tableName->name . "')",
            'mysql' => 'SELECT c.COLUMN_NAME AS name, c.COLUMN_TYPE AS declared_type,'
                . " (c.IS_NULLABLE = 'YES') AS is_nullable, c.COLUMN_DEFAULT AS dflt_value,"
                . ' k.ORDINAL_POSITION AS pk_position'
                . ' FROM information_schema.COLUMNS AS c'
                . ' LEFT JOIN information_schema.KEY_COLUMN_USAGE AS k'
                . ' ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME'
                . " AND k.COLUMN_NAME = c.COLUMN_NAME AND k.CONSTRAINT_NAME = 'PRIMARY'"
                . ' WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = ' . self::literal($tableName)
                . ' ORDER BY c.ORDINAL_POSITION',
            'pgsql' => 'SELECT c.COLUMN_NAME AS name, c.DATA_TYPE AS declared_type,'
                . " (c.IS_NULLABLE = 'YES') AS is_nullable, c.COLUMN_DEFAULT AS dflt_value,"
                . ' k.ORDINAL_POSITION AS pk_position'
                . ' FROM information_schema.COLUMNS AS c'
                . ' LEFT JOIN information_schema.KEY_COLUMN_USAGE AS k'
                . ' ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME'
                . ' AND k.COLUMN_NAME = c.COLUMN_NAME'
                . ' AND EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS AS tc'
                . ' WHERE tc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA'
                . " AND tc.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY')"
                . ' WHERE c.TABLE_SCHEMA = current_schema() AND c.TABLE_NAME = ' . self::literal($tableName)
                . ' ORDER BY c.ORDINAL_POSITION',
            default => throw new ConnectionException("Unsupported database driver '{$driver}'."),
        });
    }

    /**
     * The indexes question's spell for one table: SQLite's PRAGMA
     * list (the per-index column walk is the dialect's own
     * {@see indexColumns()} question), MySQL's STATISTICS rows and
     * PostgreSQL's catalog join, both ordered by index then column
     * position so the grouping fold reads them sequentially.
     *
     * @throws ConnectionException when the driver owns no dialect
     */
    public static function indexes(string $driver, TableName $tableName): SqlQuery
    {
        return SqlQuery::raw(match ($driver) {
            'sqlite' => "PRAGMA index_list('" . $tableName->name . "')",
            'mysql' => 'SELECT INDEX_NAME AS name, COLUMN_NAME AS column_name,'
                . ' (NON_UNIQUE = 0) AS is_unique'
                . ' FROM information_schema.STATISTICS'
                . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . self::literal($tableName)
                . ' ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            'pgsql' => 'SELECT i.relname AS name, a.attname AS column_name,'
                . ' ix.indisunique AS is_unique'
                . ' FROM pg_class AS t'
                . ' JOIN pg_index AS ix ON ix.indrelid = t.oid'
                . ' JOIN pg_class AS i ON i.oid = ix.indexrelid'
                . ' JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS k (attnum, ord) ON true'
                . ' JOIN pg_attribute AS a ON a.attrelid = t.oid AND a.attnum = k.attnum'
                . ' WHERE t.relname = ' . self::literal($tableName)
                . " AND t.relkind IN ('r', 'p')"
                . ' AND t.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema())'
                . ' ORDER BY i.relname, k.ord',
            default => throw new ConnectionException("Unsupported database driver '{$driver}'."),
        });
    }

    /**
     * The index-columns question's spell: the one question only the
     * SQLite dialect answers -- its index list carries no column
     * names, so the per-index walk is its own PRAGMA. The index name
     * arrives from the driver's own answer, so it is validated
     * through the identifier grammar before it interpolates, the same
     * one-grammar fold the table name carries.
     *
     * @throws ConnectionException when the driver is not SQLite
     * @throws QueryException      when the answered index name refuses the grammar
     */
    public static function indexColumns(string $driver, string $indexName): SqlQuery
    {
        if ($driver !== 'sqlite') {
            throw new ConnectionException(
                "The '{$driver}' dialect does not answer the index-columns question.",
            );
        }

        new SqlIdentifier()->quote($indexName, 'index name');

        return SqlQuery::raw("PRAGMA index_info('" . $indexName . "')");
    }

    /**
     * The foreign-keys question's spell for one table: SQLite's
     * `foreign_key_list`, and the two `information_schema` dialects'
     * constraint-usage rows aliased to the canonical keys
     * (`name`, `column_name`, `referenced_table_name`,
     * `referenced_column_name`), ordered by constraint then column
     * position.
     *
     * @throws ConnectionException when the driver owns no dialect
     */
    public static function foreignKeys(string $driver, TableName $tableName): SqlQuery
    {
        return SqlQuery::raw(match ($driver) {
            'sqlite' => "PRAGMA foreign_key_list('" . $tableName->name . "')",
            'mysql' => 'SELECT k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS column_name,'
                . ' k.REFERENCED_TABLE_NAME AS referenced_table_name,'
                . ' k.REFERENCED_COLUMN_NAME AS referenced_column_name'
                . ' FROM information_schema.KEY_COLUMN_USAGE AS k'
                . ' WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ' . self::literal($tableName)
                . ' AND k.POSITION_IN_UNIQUE_CONSTRAINT IS NOT NULL'
                . ' ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            'pgsql' => 'SELECT k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS column_name,'
                . ' k.REFERENCED_TABLE_NAME AS referenced_table_name,'
                . ' k.REFERENCED_COLUMN_NAME AS referenced_column_name'
                . ' FROM information_schema.KEY_COLUMN_USAGE AS k'
                . ' JOIN information_schema.TABLE_CONSTRAINTS AS tc'
                . ' ON tc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA'
                . ' AND tc.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
                . " AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'"
                . ' WHERE k.TABLE_SCHEMA = current_schema() AND k.TABLE_NAME = ' . self::literal($tableName)
                . ' ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            default => throw new ConnectionException("Unsupported database driver '{$driver}'."),
        });
    }

    /**
     * The single-quoted literal a validated name interpolates as: the
     * grammar's own vocabulary -- no quote, no dot, no whitespace --
     * is the escaping, so the PRAGMA and predicate literals the
     * spells carry can never become SQL syntax.
     */
    private static function literal(TableName $tableName): string
    {
        return "'" . $tableName->name . "'";
    }
}
