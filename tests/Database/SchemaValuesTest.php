<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\ColumnSchema;
use Zef\Framework\Database\ForeignKeySchema;
use Zef\Framework\Database\IndexSchema;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\TableName;
use Zef\Framework\Database\TableSchema;

/**
 * Pins the schema value objects' own invariants: the refusals a
 * misspelled or broken fold answer would have to pass through --
 * empty names, positionless positions, unpaired foreign-key lists,
 * the twice-answered column -- so the values stay honest even when a
 * driver (or a hand-folded row) is not.
 *
 * @internal
 */
#[CoversClass(ColumnSchema::class)]
#[CoversClass(IndexSchema::class)]
#[CoversClass(ForeignKeySchema::class)]
#[CoversClass(TableSchema::class)]
final class SchemaValuesTest extends TestCase
{
    public function testTheColumnCarriesTheFactsAsSpelled(): void
    {
        $column = new ColumnSchema('age', 'INTEGER', true, '20', null);

        self::assertSame('age', $column->name);
        self::assertSame('INTEGER', $column->declaredType);
        self::assertTrue($column->nullable);
        self::assertSame('20', $column->default);
        self::assertNull($column->primaryKeyPosition);

        $member = new ColumnSchema('id', 'int(11)', false, null, 2);

        self::assertFalse($member->nullable);
        self::assertNull($member->default);
        self::assertSame(2, $member->primaryKeyPosition);
    }

    public function testTheEmptyColumnNameRefuses(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('The reflected column name must not be empty.');

        new ColumnSchema('', 'INTEGER', true, null, null);
    }

    public function testThePrimaryKeyPositionBelowOneRefuses(): void
    {
        try {
            new ColumnSchema('id', 'INTEGER', false, null, 0);
            self::fail('position zero is no position: the driver folds it onto null, never carries it');
        } catch (QueryException $queryException) {
            self::assertSame(
                "The reflected column 'id' answers a primary-key position below one.",
                $queryException->getMessage(),
            );
        }

        $this->expectException(QueryException::class);

        new ColumnSchema('id', 'INTEGER', false, null, -1);
    }

    public function testTheIndexCarriesItsTriple(): void
    {
        $index = new IndexSchema('idx_pair', ['a', 'b'], true);

        self::assertSame('idx_pair', $index->name);
        self::assertSame(['a', 'b'], $index->columns);
        self::assertTrue($index->unique);

        $guard = new IndexSchema('sqlite_autoindex_t_1', ['c'], false);

        self::assertFalse($guard->unique);
    }

    public function testTheEmptyIndexNameRefuses(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('The reflected index name must not be empty.');

        new IndexSchema('', ['a'], false);
    }

    public function testTheIndexWithoutColumnsRefuses(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("The reflected index 'idx' covers no columns.");

        new IndexSchema('idx', [], false);
    }

    public function testTheIndexColumnListRefusals(): void
    {
        try {
            new IndexSchema('idx', ['a' => 'b'], false);
            self::fail('a map is not a list');
        } catch (QueryException $queryException) {
            self::assertSame("The reflected index 'idx' columns must be a list.", $queryException->getMessage());
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "The reflected index 'idx' column names must be non-empty strings.",
        );

        new IndexSchema('idx', ['a', ''], false);
    }

    public function testTheForeignKeyCarriesItsTripleAndItsOwnName(): void
    {
        $named = new ForeignKeySchema('orders_user_fk', ['user_id'], 'users', ['id']);

        self::assertSame('orders_user_fk', $named->name);
        self::assertSame(['user_id'], $named->columns);
        self::assertSame('users', $named->referencedTable);
        self::assertSame(['id'], $named->referencedColumns);

        $unnamed = new ForeignKeySchema(null, ['code', 'x'], 'pairs', ['a', 'b']);

        self::assertNull($unnamed->name, 'SQLite answers no constraint name: null, never fabricated');
        self::assertSame(['code', 'x'], $unnamed->columns);
        self::assertSame(['a', 'b'], $unnamed->referencedColumns);
    }

    public function testTheForeignKeyTripleRefusals(): void
    {
        try {
            new ForeignKeySchema(null, ['user_id'], '', ['id']);
            self::fail('an empty referenced table names no table');
        } catch (QueryException $queryException) {
            self::assertSame(
                'The reflected foreign key references an empty table name.',
                $queryException->getMessage(),
            );
        }

        try {
            new ForeignKeySchema(null, [], 'users', []);
            self::fail('a foreign key over no columns is no foreign key');
        } catch (QueryException $queryException) {
            self::assertSame(
                'The reflected foreign key columns must be a non-empty list.',
                $queryException->getMessage(),
            );
        }

        try {
            new ForeignKeySchema(null, ['x'], 'users', []);
            self::fail('a foreign key over no referenced columns is no foreign key');
        } catch (QueryException $queryException) {
            self::assertSame(
                'The reflected foreign key columns must be a non-empty list.',
                $queryException->getMessage(),
                'either side empty refuses at the same guard: the paired vocabulary is one vocabulary',
            );
        }

        try {
            new ForeignKeySchema(null, ['x' => 'y'], 'users', ['id']);
            self::fail('a map is not a list');
        } catch (QueryException $queryException) {
            self::assertSame(
                'The reflected foreign key column lists must be lists.',
                $queryException->getMessage(),
            );
        }

        try {
            new ForeignKeySchema(null, ['x', 'y'], 'users', ['id']);
            self::fail('the paired lengths must agree');
        } catch (QueryException $queryException) {
            self::assertSame(
                "The reflected foreign key on 'users' pairs 2 columns with 1 referenced columns.",
                $queryException->getMessage(),
            );
        }
    }

    public function testTheForeignKeyBlankNameRefusals(): void
    {
        try {
            new ForeignKeySchema(null, [''], 'users', ['id']);
            self::fail('a blank column name is no name');
        } catch (QueryException $queryException) {
            self::assertSame(
                "The reflected foreign key on 'users' column names must be non-empty strings.",
                $queryException->getMessage(),
            );
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "The reflected foreign key on 'users' column names must be non-empty strings.",
        );

        new ForeignKeySchema(null, ['user_id'], 'users', [0]);
    }

    public function testTheTableRefusesTheTwiceAnsweredColumn(): void
    {
        try {
            new TableSchema(
                new TableName('t'),
                [
                    new ColumnSchema('a', 'INTEGER', true, null, null),
                    new ColumnSchema('a', 'TEXT', true, null, null),
                ],
                [],
                [],
            );
            self::fail('a driver that answers one column twice is a driver answering a shape no table spelled');
        } catch (QueryException $queryException) {
            self::assertSame(
                "The reflected table 't' answers the column 'a' twice.",
                $queryException->getMessage(),
            );
        }
    }

    public function testTheTwiceAnsweredColumnRefusesUnderTheNumericSpellingsToo(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "The reflected table 't' answers the column '1' twice.",
        );

        new TableSchema(
            new TableName('t'),
            [
                new ColumnSchema('1', 'INTEGER', true, null, null),
                new ColumnSchema('1', 'TEXT', true, null, null),
            ],
            [],
            [],
        );
    }

    public function testTheDistinctNumericSpellingsStayDistinctColumns(): void
    {
        $tableSchema = new TableSchema(
            new TableName('t'),
            [
                new ColumnSchema('01', 'INTEGER', true, null, null),
                new ColumnSchema('1', 'TEXT', true, null, null),
            ],
            [],
            [],
        );

        self::assertSame(
            ['01', '1'],
            array_map(
                static fn (ColumnSchema $columnSchema): string => $columnSchema->name,
                $tableSchema->columns,
            ),
            'the strict comparison the duplicate guard carries keeps distinct numeric spellings'
                . ' distinct: a column named 01 is not a column named 1',
        );
    }

    public function testTheTableCarriesTheThreeAnswers(): void
    {
        $tableSchema = new TableSchema(
            new TableName('orders'),
            [new ColumnSchema('id', 'INTEGER', false, null, 1)],
            [new IndexSchema('idx', ['id'], true)],
            [new ForeignKeySchema(null, ['user_id'], 'users', ['id'])],
        );

        self::assertSame('orders', $tableSchema->name->name);
        self::assertCount(1, $tableSchema->columns);
        self::assertCount(1, $tableSchema->indexes);
        self::assertCount(1, $tableSchema->foreignKeys);
    }
}
