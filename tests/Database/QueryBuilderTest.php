<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\QueryAggregate;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Database\SqlIdentifier;
use Zef\Framework\Database\SqlQuery;

/**
 * The query builder's adversarial curriculum: SELECT/INSERT/UPDATE/DELETE
 * exactness (SQL string AND binding order), the verb-phase guards (a
 * select verb on a write query, mixed write types), the where family's
 * parameter discipline (subquery merge order, nested groups, IN lists),
 * the unbounded-write refusal both ways, and determinism -- the same
 * builder state always answers the same SQL.
 *
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
#[CoversClass(SqlQuery::class)]
#[CoversClass(QueryException::class)]
final class QueryBuilderTest extends TestCase
{
    // ------------------------------------------------------------------
    // SELECT exactness
    // ------------------------------------------------------------------

    public function testSelectStarMinimal(): void
    {
        self::assertSame('SELECT * FROM "users"', QueryBuilder::table('users')->toSql());
    }

    public function testSelectColumnsWithAliasAndParams(): void
    {
        $q = QueryBuilder::table('users', 'u')
            ->select('name AS n', 'u.age', 'email')
            ->where('age', '>', 18)
            ->where('name', '=', 'Ari')
            ->orderBy('name', 'DESC')
            ->limit(10)
            ->offset(20)
        ;

        self::assertSame(
            'SELECT "name" AS "n", "u"."age", "email" FROM "users" AS "u"'
            . ' WHERE "age" > ? AND "name" = ? ORDER BY "name" DESC LIMIT 10 OFFSET 20',
            $q->toSql(),
        );
        self::assertSame([18, 'Ari'], $q->getBindings());
    }

    public function testSelectColumnAliasCaseInsensitiveAs(): void
    {
        $q = QueryBuilder::table('users')->select('name as n');

        self::assertSame('SELECT "name" AS "n" FROM "users"', $q->toSql());
    }

    public function testAnExpressionColumnEmbedsVerbatim(): void
    {
        $q = QueryBuilder::table('users')->select(new SqlExpression('COUNT(*) AS total'));

        self::assertSame('SELECT COUNT(*) AS total FROM "users"', $q->toSql());
    }

    public function testDistinctSpellsTheKeyword(): void
    {
        $q = QueryBuilder::table('users')->select('email')->distinct();

        self::assertSame('SELECT DISTINCT "email" FROM "users"', $q->toSql());
    }

    public function testDistinctOffAnswersThePlainSelect(): void
    {
        $q = QueryBuilder::table('users')->select('email')->distinct()->distinct(false);

        self::assertSame('SELECT "email" FROM "users"', $q->toSql());
    }

    public function testSelectReplacesTheColumnSet(): void
    {
        $q = QueryBuilder::table('users')->select('a')->select('b', 'c');

        self::assertSame('SELECT "b", "c" FROM "users"', $q->toSql());
    }

    public function testSelectRequiresAColumn(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('select() requires at least one column.');

        QueryBuilder::table('users')->select();
    }

    public function testAggregateSugarSpellsTheAliasedFunction(): void
    {
        self::assertSame(
            'SELECT COUNT(*) AS aggregate FROM "users"',
            QueryBuilder::table('users')->count()->toSql(),
        );
        self::assertSame(
            'SELECT COUNT("age") AS aggregate FROM "users"',
            QueryBuilder::table('users')->count('age')->toSql(),
        );
        self::assertSame(
            'SELECT SUM("age") AS aggregate FROM "users"',
            QueryBuilder::table('users')->select(new SqlExpression('SUM("age") AS aggregate'))->toSql(),
        );
    }

    public function testTheAggregateVocabularyIsClosed(): void
    {
        // The facade's sugar fixes the function word to COUNT; the fold
        // the wider vocabulary flows through still refuses the unknown
        // function with the allowed list spelled.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "Unknown aggregate function 'MEDIAN' (allowed: COUNT, SUM, AVG, MIN, MAX).",
        );

        QueryAggregate::expression(new SqlIdentifier(), 'MEDIAN', 'age');
    }

    // ------------------------------------------------------------------
    // Joins
    // ------------------------------------------------------------------

    public function testJoinSpellsTheOnClauseWithQuotedSides(): void
    {
        $q = QueryBuilder::table('users', 'u')
            ->select('u.id')
            ->join('orders', 'o.user_id', '=', 'u.id')
        ;

        self::assertSame(
            'SELECT "u"."id" FROM "users" AS "u" INNER JOIN "orders" ON "o"."user_id" = "u"."id"',
            $q->toSql(),
        );
    }

    public function testJoinTypeCaseNormalised(): void
    {
        $q = QueryBuilder::table('users', 'u')
            ->select('u.id')
            ->join('orders', 'o.user_id', '=', 'u.id', 'LEFT')
        ;

        self::assertSame(
            'SELECT "u"."id" FROM "users" AS "u" LEFT JOIN "orders" ON "o"."user_id" = "u"."id"',
            $q->toSql(),
        );
    }

    public function testTheUnknownJoinTypeIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Unknown join type 'outer' (allowed: inner, left, right, cross).");

        QueryBuilder::table('users')->select('id')->join('orders', 'o.user_id', '=', 'u.id', 'outer');
    }

    // ------------------------------------------------------------------
    // Where family
    // ------------------------------------------------------------------

    public function testWhereAndOrWhereChainWithConnectors(): void
    {
        $q = QueryBuilder::table('users')
            ->where('age', '>', 18)
            ->orWhere('age', '<', 13)
        ;

        self::assertSame('SELECT * FROM "users" WHERE "age" > ? OR "age" < ?', $q->toSql());
        self::assertSame([18, 13], $q->getBindings());
    }

    public function testTheUnknownComparisonOperatorIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Unknown comparison operator 'LIKE' (allowed: = != <> < > <= >=).");

        QueryBuilder::table('users')->where('name', 'LIKE', 'Ari');
    }

    public function testEqualityWithNullIsRefusedTowardTheNullVerbs(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Use whereNull()/whereNotNull() instead');

        QueryBuilder::table('users')->where('deleted_at', '=', null);
    }

    public function testNullOnNonEqualityOperatorsBinds(): void
    {
        $q = QueryBuilder::table('users')->where('age', '<', null);

        self::assertSame('SELECT * FROM "users" WHERE "age" < ?', $q->toSql());
        self::assertSame([null], $q->getBindings());
    }

    public function testWhereNullAndNotNullSpellThePredicates(): void
    {
        self::assertSame(
            'SELECT * FROM "users" WHERE "deleted_at" IS NULL',
            QueryBuilder::table('users')->whereNull('deleted_at')->toSql(),
        );
        self::assertSame(
            'SELECT * FROM "users" WHERE "deleted_at" IS NOT NULL',
            QueryBuilder::table('users')->whereNotNull('deleted_at')->toSql(),
        );
    }

    public function testWhereInSpellsThePlaceholderList(): void
    {
        $q = QueryBuilder::table('users')->whereIn('id', [1, 2, 3]);

        self::assertSame('SELECT * FROM "users" WHERE "id" IN (?, ?, ?)', $q->toSql());
        self::assertSame([1, 2, 3], $q->getBindings());
    }

    public function testWhereNotInSubqueryMergesParamsAfterOuterParams(): void
    {
        $q = QueryBuilder::table('users', 'u')
            ->select('u.id')
            ->where('u.age', '>', 18)
            ->whereNotIn('u.id', QueryBuilder::table('bans')->select('user_id')->where('reason', '=', 'abuse'))
        ;

        self::assertSame(
            'SELECT "u"."id" FROM "users" AS "u" WHERE "u"."age" > ? AND "u"."id" NOT IN'
            . ' (SELECT "user_id" FROM "bans" WHERE "reason" = ?)',
            $q->toSql(),
        );
        self::assertSame([18, 'abuse'], $q->getBindings());
    }

    public function testWhereInWithTheEmptyListIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('whereIn() requires a non-empty list of values.');

        QueryBuilder::table('users')->whereIn('id', []);
    }

    public function testWhereBetweenBindsLowThenHigh(): void
    {
        $q = QueryBuilder::table('users')->whereBetween('age', 13, 19);

        self::assertSame('SELECT * FROM "users" WHERE "age" BETWEEN ? AND ?', $q->toSql());
        self::assertSame([13, 19], $q->getBindings());
    }

    public function testWhereBetweenRejectsNonScalars(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'whereBetween() low must be null, bool, int, float, string or SqlExpression (got array).',
        );

        QueryBuilder::table('users')->whereBetween('age', [], 19);
    }

    public function testWhereLikeBindsThePattern(): void
    {
        $q = QueryBuilder::table('users')->whereLike('name', 'Ar%');

        self::assertSame('SELECT * FROM "users" WHERE "name" LIKE ?', $q->toSql());
        self::assertSame(['Ar%'], $q->getBindings());
    }

    public function testWhereLikeEscapesTheWildcards(): void
    {
        $q = QueryBuilder::table('users')->whereLike('name', 'Ar\%ry_an%', true);

        self::assertSame("SELECT * FROM \"users\" WHERE \"name\" LIKE ? ESCAPE '\\'", $q->toSql());
        self::assertSame(['Ar\\\\\%ry\_an\%'], $q->getBindings());
    }

    public function testWhereLikeWithTheEmptyPatternIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('whereLike() pattern must not be empty.');

        QueryBuilder::table('users')->whereLike('name', '');
    }

    public function testASqlExpressionColumnPassesThroughInTheCondition(): void
    {
        $q = QueryBuilder::table('users')->where(new SqlExpression('LOWER(name)'), '=', 'ari');

        self::assertSame('SELECT * FROM "users" WHERE LOWER(name) = ?', $q->toSql());
    }

    // ------------------------------------------------------------------
    // Grouping, having, ordering, paging
    // ------------------------------------------------------------------

    public function testGroupByHavingOrderBySpellTheirClauses(): void
    {
        $q = QueryBuilder::table('users')
            ->select('city')
            ->groupBy('city')
            ->having('city', '!=', 'Jakarta')
            ->orderBy('city')
        ;

        self::assertSame(
            'SELECT "city" FROM "users" GROUP BY "city" HAVING "city" != ? ORDER BY "city" ASC',
            $q->toSql(),
        );
        self::assertSame(['Jakarta'], $q->getBindings());
    }

    public function testGroupByRequiresAColumn(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('groupBy() requires at least one column.');

        QueryBuilder::table('users')->groupBy();
    }

    public function testOrderByDirectionLowercaseIsNormalised(): void
    {
        $q = QueryBuilder::table('users')->orderBy('name', 'desc');

        self::assertSame('SELECT * FROM "users" ORDER BY "name" DESC', $q->toSql());
    }

    public function testTheUnknownOrderDirectionIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Unknown order direction 'SIDEWAYS' (allowed: ASC, DESC).");

        QueryBuilder::table('users')->orderBy('name', 'SIDEWAYS');
    }

    public function testLimitOffsetBoundaries(): void
    {
        self::assertSame(
            'SELECT * FROM "users" LIMIT 0',
            QueryBuilder::table('users')->limit(0)->toSql(),
        );
        self::assertSame(
            'SELECT * FROM "users" LIMIT 1 OFFSET 0',
            QueryBuilder::table('users')->limit(1)->offset(0)->toSql(),
        );
    }

    public function testNegativeLimitIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('limit() must be >= 0.');

        QueryBuilder::table('users')->limit(-1);
    }

    public function testOffsetWithoutLimitIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('offset() requires limit() to be set first.');

        QueryBuilder::table('users')->offset(10);
    }

    public function testNegativeOffsetIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('offset() must be >= 0.');

        QueryBuilder::table('users')->limit(1)->offset(-1);
    }

    // ------------------------------------------------------------------
    // INSERT
    // ------------------------------------------------------------------

    public function testInsertSpellsColumnsAndValues(): void
    {
        $q = QueryBuilder::table('users')->insertRows([['name' => 'Ari', 'age' => 20]]);

        self::assertSame('INSERT INTO "users" ("name", "age") VALUES (?, ?)', $q->toSql());
        self::assertSame(['Ari', 20], $q->getBindings());
    }

    public function testInsertRowsSpellsEveryRow(): void
    {
        $q = QueryBuilder::table('users')->insertRows([
            ['name' => 'Ari', 'age' => 20],
            ['name' => 'Budi', 'age' => 17],
        ]);

        self::assertSame('INSERT INTO "users" ("name", "age") VALUES (?, ?), (?, ?)', $q->toSql());
        self::assertSame(['Ari', 20, 'Budi', 17], $q->getBindings());
    }

    public function testInsertExpressionValuesAreEmbeddedVerbatim(): void
    {
        $q = QueryBuilder::table('users')->insertRows([['name' => 'Ari', 'created_at' => new SqlExpression('NOW()')]]);

        self::assertSame('INSERT INTO "users" ("name", "created_at") VALUES (?, NOW())', $q->toSql());
        self::assertSame(['Ari'], $q->getBindings());
    }

    public function testInsertRowsWithTheEmptyListIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('insertRows() requires at least one row.');

        QueryBuilder::table('users')->insertRows([]);
    }

    public function testInsertRowsWithTheEmptyRowIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('insertRows() row 0 must be a non-empty column => value map.');

        QueryBuilder::table('users')->insertRows([[]]);
    }

    public function testInsertRowsWithADivergentColumnSetIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('insertRows() row 1 column set differs from the first row.');

        QueryBuilder::table('users')->insertRows([
            ['name' => 'Ari'],
            ['age' => 17],
        ]);
    }

    public function testInsertRowsWithNonScalarValuesIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "insertRows() row 0 value for 'name' must be null, bool, int, float, string or SqlExpression (got array).",
        );

        QueryBuilder::table('users')->insertRows([['name' => ['nested']]]);
    }

    // ------------------------------------------------------------------
    // UPDATE / DELETE
    // ------------------------------------------------------------------

    public function testUpdateSpellsTheSetPairsThenWhereParams(): void
    {
        $q = QueryBuilder::table('users')
            ->update(['name' => 'Ari', 'age' => 21])
            ->where('id', '=', 1)
        ;

        self::assertSame('UPDATE "users" SET "name" = ?, "age" = ? WHERE "id" = ?', $q->toSql());
        self::assertSame(['Ari', 21, 1], $q->getBindings());
    }

    public function testUpdateExpressionValuesAreEmbeddedVerbatim(): void
    {
        $q = QueryBuilder::table('users')->update(['seen_at' => new SqlExpression('NOW()')])->where('id', '=', 1);

        self::assertSame('UPDATE "users" SET "seen_at" = NOW() WHERE "id" = ?', $q->toSql());
    }

    public function testUpdateWithNoPairsIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('update() requires at least one column => value pair.');

        QueryBuilder::table('users')->update([]);
    }

    public function testUpdateWithNonScalarValuesIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "update() value for 'name' must be null, bool, int, float, string or SqlExpression (got stdClass).",
        );

        QueryBuilder::table('users')->update(['name' => (object) []]);
    }

    public function testDeleteSpellsTheWhereSuffix(): void
    {
        $q = QueryBuilder::table('users')->delete()->where('id', '=', 1);

        self::assertSame('DELETE FROM "users" WHERE "id" = ?', $q->toSql());
        self::assertSame([1], $q->getBindings());
    }

    // ------------------------------------------------------------------
    // Unbounded writes
    // ------------------------------------------------------------------

    public function testUnboundedUpdateWithoutWhereIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'Refusing unbounded UPDATE without WHERE; call allowUnbounded() to confirm a full-table update.',
        );

        QueryBuilder::table('users')->update(['name' => 'X'])->build();
    }

    public function testUnboundedDeleteWithoutWhereIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'Refusing unbounded DELETE without WHERE; call allowUnbounded() to confirm a full-table delete.',
        );

        QueryBuilder::table('users')->delete()->build();
    }

    public function testAllowUnboundedMarksTheAnsweredQuery(): void
    {
        $update = QueryBuilder::table('users')->update(['name' => 'X'])->allowUnbounded()->build();
        self::assertTrue($update->unbounded);
        self::assertSame('UPDATE "users" SET "name" = ?', $update->sql);

        $delete = QueryBuilder::table('users')->delete()->allowUnbounded()->build();
        self::assertTrue($delete->unbounded);
        self::assertSame('DELETE FROM "users"', $delete->sql);
    }

    public function testTheBoundedAnswerStaysUnmarked(): void
    {
        $query = QueryBuilder::table('users')->delete()->where('id', '=', 1)->build();

        self::assertFalse($query->unbounded);
    }

    // ------------------------------------------------------------------
    // Verb-phase guards
    // ------------------------------------------------------------------

    public function testSelectOnlyVerbsAreRefusedAfterDelete(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('select() is not available on a delete query.');

        QueryBuilder::table('users')->delete()->select('id');
    }

    public function testWhereIsRefusedOnAnInsertQuery(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('WHERE clauses are not available on an insert query.');

        QueryBuilder::table('users')->insertRows([['name' => 'Ari']])->where('id', '=', 1);
    }

    public function testMixedWriteTypesAreRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('update() cannot be mixed with an existing delete query.');

        QueryBuilder::table('users')->delete()->update(['name' => 'Ari']);
    }

    public function testTheTablelessSelectIsRefusedAtBuildTime(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('Select query requires a table (call QueryBuilder::table() first).');

        new QueryBuilder()->build();
    }

    public function testMissingTableAndDataAreNamedPerType(): void
    {
        try {
            new QueryBuilder()->insertRows([['a' => 1]])->build();
            self::fail('The tableless insert must refuse.');
        } catch (QueryException $queryException) {
            self::assertSame(
                'Insert query requires a table (call QueryBuilder::table() first).',
                $queryException->getMessage(),
            );
        }

        try {
            new QueryBuilder()->update(['a' => 1])->build();
            self::fail('The tableless update must refuse.');
        } catch (QueryException $queryException) {
            self::assertSame(
                'Update query requires a table (call QueryBuilder::table() first).',
                $queryException->getMessage(),
            );
        }
    }

    // ------------------------------------------------------------------
    // Determinism
    // ------------------------------------------------------------------

    public function testTheSameStateAnswersTheSameSql(): void
    {
        $build = static fn (): string => QueryBuilder::table('users', 'u')
            ->select('u.id', 'u.name')
            ->join('orders', 'o.user_id', '=', 'u.id')
            ->where('u.age', '>', 18)
            ->orderBy('u.name', 'ASC')
            ->limit(5)
            ->toSql()
        ;

        self::assertSame($build(), $build());
    }

    public function testBuildToSqlAndGetBindingsAgree(): void
    {
        $builder = QueryBuilder::table('users')->where('id', '>', 3)->limit(2);
        $built = $builder->build();

        self::assertSame($built->sql, $builder->toSql());
        self::assertSame($built->params, $builder->getBindings());
    }

    // ------------------------------------------------------------------
    // Phase guards, every verb
    // ------------------------------------------------------------------

    /**
     * @return iterable<non-empty-string, array{non-empty-string, callable(QueryBuilder): QueryBuilder}>
     */
    public static function provideSelectSideVerbs(): iterable
    {
        yield 'select' => ['select()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->select('id')];
        yield 'distinct' => ['distinct()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->distinct()];
        yield 'join' => ['join()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->join('o', 'o.i', '=', 'u.i')];
        yield 'groupBy' => ['groupBy()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->groupBy('city')];
        yield 'having' => ['having()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->having('city', '!=', 'x')];
        yield 'orderBy' => ['orderBy()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->orderBy('name')];
        yield 'limit' => ['limit()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->limit(1)];
        yield 'offset' => ['offset()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->offset(1)];
    }

    #[DataProvider('provideSelectSideVerbs')]
    public function testEverySelectSideVerbRefusesTheWritePhases(string $method, callable $verb): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("{$method} is not available on a delete query.");

        $verb(QueryBuilder::table('users')->delete());
    }

    /**
     * @return iterable<non-empty-string, array{callable(QueryBuilder): QueryBuilder}>
     */
    public static function provideWhereFamilyVerbs(): iterable
    {
        yield 'where' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->where('id', '=', 1)];
        yield 'orWhere' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->orWhere('id', '=', 1)];
        yield 'whereIn' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereIn('id', [1])];
        yield 'whereNotIn' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereNotIn('id', [1])];
        yield 'whereNull' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereNull('gone')];
        yield 'whereNotNull' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereNotNull('gone')];
        yield 'whereBetween' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereBetween('age', 1, 2)];
        yield 'whereLike' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereLike('name', 'A%')];
    }

    #[DataProvider('provideWhereFamilyVerbs')]
    public function testEveryWhereVerbRefusesTheInsertPhase(callable $verb): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('WHERE clauses are not available on an insert query.');

        $verb(QueryBuilder::table('users')->insertRows([['name' => 'Ari']]));
    }
}
