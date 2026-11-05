<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\ConnectionConfig;
use Zef\Framework\Adapters\Database\PdoConnection;
use Zef\Framework\Adapters\Database\SchemaCells;
use Zef\Framework\Adapters\Database\SchemaColumns;
use Zef\Framework\Adapters\Database\SchemaDialect;
use Zef\Framework\Adapters\Database\SchemaFlags;
use Zef\Framework\Adapters\Database\SchemaForeignKeys;
use Zef\Framework\Adapters\Database\SchemaIndexes;
use Zef\Framework\Adapters\Database\SchemaReflection;
use Zef\Framework\Database\ColumnSchema;
use Zef\Framework\Database\ConnectionException;
use Zef\Framework\Database\ForeignKeySchema;
use Zef\Framework\Database\IndexSchema;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlQuery;
use Zef\Framework\Database\TableName;
use Zef\Framework\Database\TableSchema;

/**
 * Pins the schema-reflection family over the real SQLite socket: the
 * tables inventory in the driver's own order (the internal
 * `sqlite_sequence` excluded by the spell), the columns as the driver
 * spells them (the declared defaults in their own literal spelling,
 * the `INTEGER PRIMARY KEY` notnull quirk carried honestly, the
 * composite key's own positions), the indexes as answered (the
 * UNIQUE constraint's own autoindex guard travelling with the rest,
 * unfiltered), the foreign keys as answered (no name on SQLite,
 * multi-column pairs in the answer's own order), and the miss that
 * refuses loudly instead of answering a silent empty shape.
 *
 * The two `information_schema` dialects land spell-only in this
 * slice -- CI's socket reaches SQLite alone -- so their exact spells
 * and their row folds over the documented aliased shapes are pinned
 * here as recorded fixtures, with the live-socket posture the family's
 * own named follow-up.
 *
 * @internal
 */
#[CoversClass(SchemaReflection::class)]
#[CoversClass(SchemaDialect::class)]
#[CoversClass(SchemaCells::class)]
#[CoversClass(SchemaFlags::class)]
#[CoversClass(SchemaColumns::class)]
#[CoversClass(SchemaIndexes::class)]
#[CoversClass(SchemaForeignKeys::class)]
#[CoversClass(TableName::class)]
#[CoversClass(ColumnSchema::class)]
#[CoversClass(IndexSchema::class)]
#[CoversClass(ForeignKeySchema::class)]
#[CoversClass(TableSchema::class)]
final class SchemaReflectionTest extends TestCase
{
    private SchemaReflection $schemaReflection;

    #[Override]
    protected function setUp(): void
    {
        $pdoConnection = new PdoConnection(ConnectionConfig::fromArray([
            'driver' => 'sqlite',
            'dbname' => ':memory:',
        ]));
        $pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE users ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,'
            . ' age INTEGER DEFAULT 20, ratio REAL DEFAULT 1.5, flag INTEGER DEFAULT 0,'
            . " note TEXT DEFAULT NULL, bio TEXT DEFAULT 'hi',"
            . " created_at TEXT DEFAULT (datetime('now')), untyped)",
        ));
        $pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE pairs (a INTEGER, b TEXT, PRIMARY KEY (a, b))',
        ));
        $pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE ties (x INTEGER, y TEXT,'
            . ' FOREIGN KEY (x, y) REFERENCES pairs (a, b))',
        ));
        $pdoConnection->execute(SqlQuery::raw(
            'CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, code TEXT,'
            . ' UNIQUE (code), CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users (id),'
            . ' FOREIGN KEY (code) REFERENCES users (name))',
        ));
        $pdoConnection->execute(SqlQuery::raw(
            'CREATE INDEX idx_orders_code ON orders (code)',
        ));
        $pdoConnection->execute(SqlQuery::raw(
            'CREATE UNIQUE INDEX idx_orders_user ON orders (user_id, code)',
        ));

        $this->schemaReflection = new SchemaReflection($pdoConnection, 'sqlite');
    }

    public function testTablesAnswersTheDeploymentsOwnTablesInNameOrder(): void
    {
        $tables = $this->schemaReflection->tables();

        self::assertContainsOnlyInstancesOf(TableName::class, $tables);
        self::assertSame(
            ['orders', 'pairs', 'ties', 'users'],
            array_map(static fn (TableName $tableName): string => $tableName->name, $tables),
            'the internal sqlite_sequence table stays excluded:'
                . " it is the driver's own bookkeeping, not the deployment's shape",
        );
    }

    public function testTheUsersTableAnswersTheColumnsTheDriverSpelled(): void
    {
        $columns = $this->columnsByName($this->schemaReflection->table(new TableName('users')));

        self::assertSame(
            ['id', 'name', 'age', 'ratio', 'flag', 'note', 'bio', 'created_at', 'untyped'],
            array_keys($columns),
            "the columns arrive in the driver's own order",
        );

        $id = $columns['id'];
        self::assertSame('INTEGER', $id->declaredType);
        self::assertTrue(
            $id->nullable,
            'SQLite answers notnull 0 for the INTEGER PRIMARY KEY quirk, and the fold carries the answer honestly',
        );
        self::assertNull($id->default);
        self::assertSame(1, $id->primaryKeyPosition);

        $name = $columns['name'];
        self::assertSame('TEXT', $name->declaredType);
        self::assertFalse($name->nullable);
        self::assertNull($name->default);
        self::assertNull($name->primaryKeyPosition);

        self::assertSame('INTEGER', $columns['age']->declaredType);
        self::assertTrue($columns['age']->nullable);
        self::assertSame(
            '20',
            $columns['age']->default,
            "the declared default travels in the driver's own literal spelling",
        );
        self::assertNull($columns['age']->primaryKeyPosition);

        self::assertSame('REAL', $columns['ratio']->declaredType);
        self::assertSame('1.5', $columns['ratio']->default);
        self::assertSame('INTEGER', $columns['flag']->declaredType);
        self::assertSame('0', $columns['flag']->default);

        self::assertSame('TEXT', $columns['note']->declaredType);
        self::assertSame(
            'NULL',
            $columns['note']->default,
            'an explicit DEFAULT NULL stays the word the driver answered',
        );

        self::assertSame('TEXT', $columns['bio']->declaredType);
        self::assertSame(
            "'hi'",
            $columns['bio']->default,
            'a string default stays the quoted literal the driver answered',
        );

        self::assertSame('TEXT', $columns['created_at']->declaredType);
        self::assertSame("datetime('now')", $columns['created_at']->default, 'an expression default stays its own SQL');

        self::assertSame(
            '',
            $columns['untyped']->declaredType,
            'a typeless SQLite column carries the empty spelling the driver answered',
        );
        self::assertTrue($columns['untyped']->nullable);
        self::assertNull($columns['untyped']->default);
        self::assertNull($columns['untyped']->primaryKeyPosition);
    }

    public function testThePairsTableAnswersTheCompositePrimaryKeyPositions(): void
    {
        $columns = $this->columnsByName($this->schemaReflection->table(new TableName('pairs')));

        self::assertSame(['a', 'b'], array_keys($columns));
        self::assertSame(1, $columns['a']->primaryKeyPosition);
        self::assertSame(2, $columns['b']->primaryKeyPosition);
        self::assertTrue(
            $columns['a']->nullable,
            "the composite-key members carry SQLite's own notnull answer, unedited",
        );
    }

    public function testTheOrdersTableAnswersTheIndexesAsTheDriverSpellsThem(): void
    {
        $indexes = $this->schemaReflection->table(new TableName('orders'))->indexes;

        self::assertCount(3, $indexes);

        [$userIndex, $codeIndex, $autoIndex] = $indexes;

        self::assertSame('idx_orders_user', $userIndex->name);
        self::assertSame(['user_id', 'code'], $userIndex->columns);
        self::assertTrue($userIndex->unique);

        self::assertSame('idx_orders_code', $codeIndex->name);
        self::assertSame(['code'], $codeIndex->columns);
        self::assertFalse($codeIndex->unique);

        self::assertSame(
            'sqlite_autoindex_orders_1',
            $autoIndex->name,
            "the UNIQUE constraint's own guard travels with the rest, unfiltered",
        );
        self::assertSame(['code'], $autoIndex->columns);
        self::assertTrue($autoIndex->unique);
    }

    public function testTheOrdersTableAnswersTheForeignKeysAsTheDriverSpellsThem(): void
    {
        $foreignKeys = $this->schemaReflection->table(new TableName('orders'))->foreignKeys;

        self::assertCount(2, $foreignKeys);

        [$codeKey, $userKey] = $foreignKeys;

        self::assertNull($codeKey->name, 'SQLite answers no constraint name: null, never a fabricated one');
        self::assertSame(['code'], $codeKey->columns);
        self::assertSame('users', $codeKey->referencedTable);
        self::assertSame(['name'], $codeKey->referencedColumns);

        self::assertNull($userKey->name, 'the CONSTRAINT spelling names nothing SQLite answers either');
        self::assertSame(['user_id'], $userKey->columns);
        self::assertSame('users', $userKey->referencedTable);
        self::assertSame(['id'], $userKey->referencedColumns);
    }

    public function testTheTiesTablePairsTheMultiColumnForeignKeyInAnswerOrder(): void
    {
        $foreignKeys = $this->schemaReflection->table(new TableName('ties'))->foreignKeys;

        self::assertCount(1, $foreignKeys);

        self::assertNull($foreignKeys[0]->name);
        self::assertSame(['x', 'y'], $foreignKeys[0]->columns);
        self::assertSame('pairs', $foreignKeys[0]->referencedTable);
        self::assertSame(['a', 'b'], $foreignKeys[0]->referencedColumns);
    }

    public function testTheNameTheDriverDoesNotKnowRefusesLoudly(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "The driver answered no columns for table 'missing' -- the reflected name owns no table.",
        );

        $this->schemaReflection->table(new TableName('missing'));
    }

    public function testTheSqliteDialectSpellsThePragmaFamily(): void
    {
        $tableName = new TableName('users');

        self::assertSame(
            'SELECT "name" AS name FROM sqlite_master'
            . " WHERE type = 'table' AND name NOT LIKE 'sqlite\\_%' ESCAPE '\\'"
            . ' ORDER BY "name"',
            SchemaDialect::tables('sqlite')->sql,
        );
        self::assertSame(
            "PRAGMA table_info('users')",
            SchemaDialect::columns('sqlite', $tableName)->sql,
        );
        self::assertSame(
            "PRAGMA index_list('users')",
            SchemaDialect::indexes('sqlite', $tableName)->sql,
        );
        self::assertSame(
            "PRAGMA index_info('idx_orders_code')",
            SchemaDialect::indexColumns('sqlite', 'idx_orders_code')->sql,
        );
        self::assertSame(
            "PRAGMA foreign_key_list('users')",
            SchemaDialect::foreignKeys('sqlite', $tableName)->sql,
        );
        self::assertSame([], SchemaDialect::tables('sqlite')->params);
    }

    public function testTheMysqlDialectSpellsInformationSchema(): void
    {
        $tableName = new TableName('users');

        self::assertSame(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES'
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
            . ' ORDER BY TABLE_NAME',
            SchemaDialect::tables('mysql')->sql,
        );
        self::assertSame(
            'SELECT c.COLUMN_NAME AS name, c.COLUMN_TYPE AS declared_type,'
            . " (c.IS_NULLABLE = 'YES') AS is_nullable, c.COLUMN_DEFAULT AS dflt_value,"
            . ' k.ORDINAL_POSITION AS pk_position'
            . ' FROM information_schema.COLUMNS AS c'
            . ' LEFT JOIN information_schema.KEY_COLUMN_USAGE AS k'
            . ' ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME'
            . " AND k.COLUMN_NAME = c.COLUMN_NAME AND k.CONSTRAINT_NAME = 'PRIMARY'"
            . " WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = 'users'"
            . ' ORDER BY c.ORDINAL_POSITION',
            SchemaDialect::columns('mysql', $tableName)->sql,
        );
        self::assertSame(
            'SELECT INDEX_NAME AS name, COLUMN_NAME AS column_name,'
            . ' (NON_UNIQUE = 0) AS is_unique'
            . ' FROM information_schema.STATISTICS'
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
            . ' ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            SchemaDialect::indexes('mysql', $tableName)->sql,
        );
        self::assertSame(
            'SELECT k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS column_name,'
            . ' k.REFERENCED_TABLE_NAME AS referenced_table_name,'
            . ' k.REFERENCED_COLUMN_NAME AS referenced_column_name'
            . ' FROM information_schema.KEY_COLUMN_USAGE AS k'
            . " WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = 'users'"
            . ' AND k.POSITION_IN_UNIQUE_CONSTRAINT IS NOT NULL'
            . ' ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            SchemaDialect::foreignKeys('mysql', $tableName)->sql,
        );
    }

    public function testThePgsqlDialectSpellsInformationSchema(): void
    {
        $tableName = new TableName('users');

        self::assertSame(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES'
            . " WHERE TABLE_SCHEMA = current_schema() AND TABLE_TYPE = 'BASE TABLE'"
            . ' ORDER BY TABLE_NAME',
            SchemaDialect::tables('pgsql')->sql,
        );
        self::assertSame(
            'SELECT c.COLUMN_NAME AS name, c.DATA_TYPE AS declared_type,'
            . " (c.IS_NULLABLE = 'YES') AS is_nullable, c.COLUMN_DEFAULT AS dflt_value,"
            . ' k.ORDINAL_POSITION AS pk_position'
            . ' FROM information_schema.COLUMNS AS c'
            . ' LEFT JOIN information_schema.KEY_COLUMN_USAGE AS k'
            . ' ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME'
            . ' AND k.COLUMN_NAME = c.COLUMN_NAME'
            . ' AND EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS AS tc'
            . ' WHERE tc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA'
            . " AND tc.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY')"
            . " WHERE c.TABLE_SCHEMA = current_schema() AND c.TABLE_NAME = 'users'"
            . ' ORDER BY c.ORDINAL_POSITION',
            SchemaDialect::columns('pgsql', $tableName)->sql,
        );
        self::assertSame(
            'SELECT i.relname AS name, a.attname AS column_name,'
            . ' ix.indisunique AS is_unique'
            . ' FROM pg_class AS t'
            . ' JOIN pg_index AS ix ON ix.indrelid = t.oid'
            . ' JOIN pg_class AS i ON i.oid = ix.indexrelid'
            . ' JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS k (attnum, ord) ON true'
            . ' JOIN pg_attribute AS a ON a.attrelid = t.oid AND a.attnum = k.attnum'
            . " WHERE t.relname = 'users' AND t.relkind IN ('r', 'p')"
            . ' AND t.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = current_schema())'
            . ' ORDER BY i.relname, k.ord',
            SchemaDialect::indexes('pgsql', $tableName)->sql,
        );
        self::assertSame(
            'SELECT k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS column_name,'
            . ' k.REFERENCED_TABLE_NAME AS referenced_table_name,'
            . ' k.REFERENCED_COLUMN_NAME AS referenced_column_name'
            . ' FROM information_schema.KEY_COLUMN_USAGE AS k'
            . ' JOIN information_schema.TABLE_CONSTRAINTS AS tc'
            . ' ON tc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA'
            . ' AND tc.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
            . " AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'"
            . " WHERE k.TABLE_SCHEMA = current_schema() AND k.TABLE_NAME = 'users'"
            . ' ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            SchemaDialect::foreignKeys('pgsql', $tableName)->sql,
        );
    }

    public function testTheDialectRefusesTheUnknownDriverOnEveryQuestion(): void
    {
        $questions = [
            static fn (): SqlQuery => SchemaDialect::tables('oracle'),
            static fn (): SqlQuery => SchemaDialect::columns('oracle', new TableName('users')),
            static fn (): SqlQuery => SchemaDialect::indexes('oracle', new TableName('users')),
            static fn (): SqlQuery => SchemaDialect::foreignKeys('oracle', new TableName('users')),
        ];

        foreach ($questions as $question) {
            try {
                $question();
                self::fail('the oracle driver owns no dialect');
            } catch (ConnectionException $connectionException) {
                self::assertSame(
                    "Unsupported database driver 'oracle'.",
                    $connectionException->getMessage(),
                );
            }
        }
    }

    public function testTheIndexColumnsQuestionIsTheSqliteDialectsOwn(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageIsOrContains(
            "The 'mysql' dialect does not answer the index-columns question.",
        );

        SchemaDialect::indexColumns('mysql', 'idx');
    }

    public function testTheAnsweredIndexNameIsGrammarValidatedBeforeItInterpolates(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid index name 'bad_name!'.");

        SchemaDialect::indexColumns('sqlite', 'bad_name!');
    }

    public function testTheMysqlColumnsFoldReadsTheAliasedShape(): void
    {
        $columns = SchemaColumns::fold('mysql', [
            [
                'name' => 'id',
                'declared_type' => 'int(11)',
                'is_nullable' => 0,
                'dflt_value' => null,
                'pk_position' => 1,
            ],
            [
                'name' => 'v',
                'declared_type' => 'varchar(64)',
                'is_nullable' => 1,
                'dflt_value' => '0',
                'pk_position' => 2,
            ],
            ['name' => 'bare', 'declared_type' => 'text'],
        ]);

        self::assertCount(3, $columns);

        self::assertSame('id', $columns[0]->name);
        self::assertSame('int(11)', $columns[0]->declaredType, 'the declared type travels as the driver spells it');
        self::assertFalse($columns[0]->nullable);
        self::assertNull($columns[0]->default);
        self::assertSame(1, $columns[0]->primaryKeyPosition);

        self::assertSame('v', $columns[1]->name);
        self::assertSame('varchar(64)', $columns[1]->declaredType);
        self::assertTrue($columns[1]->nullable);
        self::assertSame('0', $columns[1]->default);
        self::assertSame(2, $columns[1]->primaryKeyPosition);

        self::assertFalse($columns[2]->nullable, "an absent is_nullable cell answers the flag vocabulary's negative");
        self::assertNull($columns[2]->default, 'an absent default cell answers no default');
        self::assertNull($columns[2]->primaryKeyPosition, "an absent pk_position cell answers the LEFT JOIN's miss");
    }

    public function testThePgsqlColumnsFoldReadsTheAliasedShape(): void
    {
        $columns = SchemaColumns::fold('pgsql', [
            [
                'name' => 'id',
                'declared_type' => 'integer',
                'is_nullable' => false,
                'dflt_value' => null,
                'pk_position' => 1,
            ],
            [
                'name' => 'body',
                'declared_type' => 'text',
                'is_nullable' => true,
                'dflt_value' => "nextval('t_id_seq'::regclass)",
                'pk_position' => null,
            ],
        ]);

        self::assertSame('integer', $columns[0]->declaredType);
        self::assertFalse($columns[0]->nullable, 'the native bool folds onto the verdict vocabulary');
        self::assertSame(1, $columns[0]->primaryKeyPosition);

        self::assertSame('text', $columns[1]->declaredType);
        self::assertTrue($columns[1]->nullable);
        self::assertSame("nextval('t_id_seq'::regclass)", $columns[1]->default);
        self::assertNull($columns[1]->primaryKeyPosition);
    }

    public function testTheColumnsFoldRefusesTheCellsOutsideTheVocabulary(): void
    {
        $rows = [
            [
                [
                    'name' => 5,
                    'declared_type' => 'int',
                    'is_nullable' => 0,
                    'dflt_value' => null,
                    'pk_position' => null,
                ],
                "The driver answered a non-string 'name' cell in a reflected row.",
            ],
            [
                [
                    'name' => 'a',
                    'declared_type' => 'int',
                    'is_nullable' => 'YES',
                    'dflt_value' => null,
                    'pk_position' => null,
                ],
                "The driver answered a non-flag 'is_nullable' cell in a reflected row.",
            ],
            [
                [
                    'name' => 'a',
                    'declared_type' => 'int',
                    'is_nullable' => 0,
                    'dflt_value' => null,
                    'pk_position' => '1',
                ],
                "The driver answered a non-integer 'pk_position' cell in a reflected row.",
            ],
            [
                [
                    'name' => 'a',
                    'declared_type' => 'int',
                    'is_nullable' => 0,
                    'dflt_value' => [],
                    'pk_position' => null,
                ],
                "The driver answered a non-scalar 'dflt_value' cell in a reflected row.",
            ],
        ];

        foreach ($rows as [$row, $refusal]) {
            try {
                SchemaColumns::fold('mysql', [$row]);
                self::fail("the broken cell refuses: {$refusal}");
            } catch (QueryException $queryException) {
                self::assertSame($refusal, $queryException->getMessage());
            }
        }
    }

    public function testTheSqliteColumnsFoldRefusesTheNotnullCellOutsideTheVocabulary(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'The driver answered a notnull cell outside the zero-or-one vocabulary.',
        );

        SchemaColumns::fold('sqlite', [
            ['name' => 'a', 'type' => 'TEXT', 'notnull' => 2, 'dflt_value' => null, 'pk' => 0],
        ]);
    }

    public function testTheSqliteColumnsFoldRefusesTheMissingPkCell(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "The driver answered a missing 'pk' cell in a reflected row.",
        );

        SchemaColumns::fold('sqlite', [
            ['name' => 'a', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null],
        ]);
    }

    public function testTheInformationSchemaIndexesFoldGroupsTheOrderedRows(): void
    {
        $indexes = SchemaIndexes::fold('mysql', [
            ['name' => 'idx_pair', 'column_name' => 'a', 'is_unique' => 1],
            ['name' => 'idx_pair', 'column_name' => 'b', 'is_unique' => 1],
            ['name' => '123', 'column_name' => 'c', 'is_unique' => 0],
        ], static function (): array {
            throw new QueryException('The information_schema dialects never walk the column seam.');
        });

        self::assertSame('idx_pair', $indexes[0]->name);
        self::assertSame(['a', 'b'], $indexes[0]->columns, "the covered columns arrive in the spell's own order");
        self::assertTrue($indexes[0]->unique);

        self::assertSame('123', $indexes[1]->name, 'a numeric index name survives the grouping round-trip');
        self::assertSame(['c'], $indexes[1]->columns);
        self::assertFalse($indexes[1]->unique);
    }

    public function testThePgsqlIndexesFoldReadsTheSameAliasedShape(): void
    {
        $indexes = SchemaIndexes::fold('pgsql', [
            ['name' => 'idx_pair', 'column_name' => 'a', 'is_unique' => true],
            ['name' => 'idx_pair', 'column_name' => 'b', 'is_unique' => true],
        ], static function (): array {
            throw new QueryException('The information_schema dialects never walk the column seam.');
        });

        self::assertSame('idx_pair', $indexes[0]->name);
        self::assertSame(['a', 'b'], $indexes[0]->columns);
        self::assertTrue($indexes[0]->unique, 'the native bool verdict folds through the same aliased shape');
    }

    public function testTheSqliteIndexesFoldWalksTheColumnSeamPerIndex(): void
    {
        $walked = [];

        $indexes = SchemaIndexes::fold('sqlite', [
            ['seq' => 0, 'name' => 'idx_pair', 'unique' => 1, 'origin' => 'c', 'partial' => 0],
            ['seq' => 1, 'name' => 'idx_one', 'unique' => 0, 'origin' => 'c', 'partial' => 0],
        ], static function (string $indexName) use (&$walked): array {
            $walked[] = $indexName;

            return $indexName === 'idx_pair' ? ['a', 'b'] : ['c'];
        });

        self::assertSame(['idx_pair', 'idx_one'], $walked, 'the seam walks once per answered index');
        self::assertSame('idx_pair', $indexes[0]->name);
        self::assertSame(['a', 'b'], $indexes[0]->columns);
        self::assertTrue($indexes[0]->unique);
        self::assertSame('idx_one', $indexes[1]->name);
        self::assertSame(['c'], $indexes[1]->columns);
        self::assertFalse($indexes[1]->unique);
    }

    public function testTheIndexesFoldRefusesTheBrokenUniqueCell(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("The driver answered a non-flag 'unique' cell in a reflected row.");

        SchemaIndexes::fold('sqlite', [
            ['seq' => 0, 'name' => 'idx', 'unique' => 'yes', 'origin' => 'c', 'partial' => 0],
        ], static fn (): array => ['a']);
    }

    public function testTheInformationSchemaForeignKeysFoldGroupsTheOrderedRows(): void
    {
        $foreignKeys = SchemaForeignKeys::fold('pgsql', [
            [
                'name' => 'orders_user_fk',
                'column_name' => 'user_id',
                'referenced_table_name' => 'users',
                'referenced_column_name' => 'id',
            ],
            [
                'name' => '404',
                'column_name' => 'code',
                'referenced_table_name' => 'codes',
                'referenced_column_name' => 'c',
            ],
            [
                'name' => '404',
                'column_name' => 'x',
                'referenced_table_name' => 'codes',
                'referenced_column_name' => 'y',
            ],
        ]);

        self::assertSame('orders_user_fk', $foreignKeys[0]->name);
        self::assertSame(['user_id'], $foreignKeys[0]->columns);
        self::assertSame('users', $foreignKeys[0]->referencedTable);
        self::assertSame(['id'], $foreignKeys[0]->referencedColumns);

        self::assertSame('404', $foreignKeys[1]->name, 'a numeric constraint name survives the grouping round-trip');
        self::assertSame(['code', 'x'], $foreignKeys[1]->columns);
        self::assertSame('codes', $foreignKeys[1]->referencedTable);
        self::assertSame(['c', 'y'], $foreignKeys[1]->referencedColumns);
    }

    public function testTheMysqlForeignKeysFoldReadsTheSameAliasedShape(): void
    {
        $foreignKeys = SchemaForeignKeys::fold('mysql', [
            [
                'name' => 'orders_code_fk',
                'column_name' => 'code',
                'referenced_table_name' => 'users',
                'referenced_column_name' => 'name',
            ],
        ]);

        self::assertSame('orders_code_fk', $foreignKeys[0]->name);
        self::assertSame(['code'], $foreignKeys[0]->columns);
        self::assertSame('users', $foreignKeys[0]->referencedTable);
        self::assertSame(['name'], $foreignKeys[0]->referencedColumns);
    }

    public function testTheRowFoldsRefuseTheUnknownDriver(): void
    {
        $folds = [
            static fn (): array => SchemaColumns::fold('oracle', []),
            static fn (): array => SchemaIndexes::fold('oracle', [], static fn (): array => []),
            static fn (): array => SchemaForeignKeys::fold('oracle', []),
        ];

        foreach ($folds as $fold) {
            try {
                $fold();
                self::fail('the oracle driver owns no dialect');
            } catch (ConnectionException $connectionException) {
                self::assertSame(
                    "Unsupported database driver 'oracle'.",
                    $connectionException->getMessage(),
                );
            }
        }
    }

    /**
     * @param TableSchema $tableSchema one reflected table
     *
     * @return array<string, ColumnSchema> the columns by name, answer order kept
     */
    private function columnsByName(TableSchema $tableSchema): array
    {
        $columns = [];

        foreach ($tableSchema->columns as $column) {
            $columns[$column->name] = $column;
        }

        return $columns;
    }
}
