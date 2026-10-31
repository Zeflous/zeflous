<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zef\Framework\Database\DatabaseException;
use Zef\Framework\Database\QueryAggregate;
use Zef\Framework\Database\QueryBuild;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\QueryColumns;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\QueryFragment;
use Zef\Framework\Database\QueryHaving;
use Zef\Framework\Database\QueryInsertAssembly;
use Zef\Framework\Database\QueryInsertRows;
use Zef\Framework\Database\QueryJoin;
use Zef\Framework\Database\QueryOrder;
use Zef\Framework\Database\QueryPaging;
use Zef\Framework\Database\QueryPhase;
use Zef\Framework\Database\QueryPredicate;
use Zef\Framework\Database\QuerySelectAssembly;
use Zef\Framework\Database\QuerySnapshot;
use Zef\Framework\Database\QueryUpdateAssembly;
use Zef\Framework\Database\QueryUpdatePairs;
use Zef\Framework\Database\QueryValues;
use Zef\Framework\Database\QueryWhere;
use Zef\Framework\Database\QueryWhereState;
use Zef\Framework\Database\SqlColumn;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Database\SqlIdentifier;
use Zef\Framework\Database\SqlQuery;

/**
 * The query builder's adversarial curriculum: SELECT/INSERT/UPDATE/DELETE
 * exactness (SQL string AND binding order), the verb-phase guards (a
 * select verb on a write query, mixed write types), the where family's
 * parameter discipline (subquery merge order, nested groups, IN lists),
 * the unbounded-write refusal both ways, the negated polarity the
 * predicate folds carry, and determinism -- the same builder state
 * always answers the same SQL.
 *
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
#[CoversClass(SqlQuery::class)]
#[CoversClass(QueryException::class)]
#[CoversClass(DatabaseException::class)]
#[CoversClass(QueryPhase::class)]
#[CoversClass(QueryColumns::class)]
#[CoversClass(QueryAggregate::class)]
#[CoversClass(QueryPaging::class)]
#[CoversClass(QueryJoin::class)]
#[CoversClass(QueryOrder::class)]
#[CoversClass(QueryValues::class)]
#[CoversClass(QueryPredicate::class)]
#[CoversClass(QueryFragment::class)]
#[CoversClass(QueryWhere::class)]
#[CoversClass(QueryWhereState::class)]
#[CoversClass(QueryHaving::class)]
#[CoversClass(QueryInsertRows::class)]
#[CoversClass(QueryUpdatePairs::class)]
#[CoversClass(QuerySnapshot::class)]
#[CoversClass(QueryBuild::class)]
#[CoversClass(QuerySelectAssembly::class)]
#[CoversClass(QueryInsertAssembly::class)]
#[CoversClass(QueryUpdateAssembly::class)]
#[CoversClass(SqlIdentifier::class)]
#[CoversClass(SqlColumn::class)]
#[CoversClass(SqlExpression::class)]
final class QueryBuilderTest extends TestCase
{
    // ------------------------------------------------------------------
    // The facade's entry
    // ------------------------------------------------------------------

    public function testTableIsTheOnlyEntryTheConstructorIsPrivate(): void
    {
        // The tableless refusals the assemblies once carried are gone:
        // a query without its table is a state the construction itself
        // refuses, so the pin is the constructor's own visibility.
        self::assertTrue(new ReflectionMethod(QueryBuilder::class, '__construct')->isPrivate());
    }

    public function testTableQuotesTheNameAndCarriesTheAlias(): void
    {
        $query = QueryBuilder::table('users', 'u')->select('u.id')->build();

        self::assertSame('SELECT "u"."id" FROM "users" AS "u"', $query->sql);
        self::assertSame([], $query->params);
    }

    // ------------------------------------------------------------------
    // SELECT exactness
    // ------------------------------------------------------------------

    public function testSelectStarMinimal(): void
    {
        self::assertSame('SELECT * FROM "users"', QueryBuilder::table('users')->build()->sql);
    }

    public function testSelectColumnsWithAliasAndParams(): void
    {
        $query = QueryBuilder::table('users', 'u')
            ->select('name AS n', 'u.age', 'email')
            ->where('age', '>', 18)
            ->where('name', '=', 'Ari')
            ->orderBy('name', 'DESC')
            ->limit(10, 20)
            ->build()
        ;

        self::assertSame(
            'SELECT "name" AS "n", "u"."age", "email" FROM "users" AS "u"'
            . ' WHERE "age" > ? AND "name" = ? ORDER BY "name" DESC LIMIT 10 OFFSET 20',
            $query->sql,
        );
        self::assertSame([18, 'Ari'], $query->params);
    }

    public function testSelectColumnAliasCaseInsensitiveAs(): void
    {
        $query = QueryBuilder::table('users')->select('name as n')->build();

        self::assertSame('SELECT "name" AS "n" FROM "users"', $query->sql);
    }

    public function testAnExpressionColumnEmbedsVerbatim(): void
    {
        $query = QueryBuilder::table('users')->select(new SqlExpression('COUNT(*) AS total'))->build();

        self::assertSame('SELECT COUNT(*) AS total FROM "users"', $query->sql);
    }

    public function testDistinctSpellsTheKeyword(): void
    {
        $query = QueryBuilder::table('users')->select('email')->distinct()->build();

        self::assertSame('SELECT DISTINCT "email" FROM "users"', $query->sql);
    }

    public function testDistinctOffAnswersThePlainSelect(): void
    {
        $query = QueryBuilder::table('users')->select('email')->distinct()->distinct(false)->build();

        self::assertSame('SELECT "email" FROM "users"', $query->sql);
    }

    public function testSelectReplacesTheColumnSet(): void
    {
        $query = QueryBuilder::table('users')->select('a')->select('b', 'c')->build();

        self::assertSame('SELECT "b", "c" FROM "users"', $query->sql);
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
            QueryBuilder::table('users')->count()->build()->sql,
        );
        self::assertSame(
            'SELECT COUNT("age") AS aggregate FROM "users"',
            QueryBuilder::table('users')->count('age')->build()->sql,
        );
        self::assertSame(
            'SELECT SUM("age") AS aggregate FROM "users"',
            QueryBuilder::table('users')->select(new SqlExpression('SUM("age") AS aggregate'))->build()->sql,
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

    public function testTheAggregateVocabularyFoldsMultibyteCaseSpellings(): void
    {
        // The long-s spelling folds onto SUM under the character map
        // (mb) while the byte map leaves it unrecognised -- the fold's
        // own vocabulary answers the folded word.
        self::assertSame(
            'SUM("age") AS aggregate',
            (string) QueryAggregate::expression(new SqlIdentifier(), 'ſum', 'age'),
        );
    }

    // ------------------------------------------------------------------
    // Joins
    // ------------------------------------------------------------------

    public function testTheUnknownJoinTypeIsNamedInItsFoldedSpelling(): void
    {
        // The refusal echoes the case-folded type, not the raw input:
        // the full-width spelling folds under the character map while
        // the byte map would leave it untouched.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "Unknown join type 'ｌｅｆｔ' (allowed: inner, left, right, cross).",
        );

        QueryBuilder::table('users')->select('id')->join('orders', 'o.user_id', '=', 'u.id', 'ＬＥＦＴ');
    }

    public function testJoinSpellsTheOnClauseWithQuotedSides(): void
    {
        $query = QueryBuilder::table('users', 'u')
            ->select('u.id')
            ->join('orders', 'o.user_id', '=', 'u.id')
            ->build()
        ;

        self::assertSame(
            'SELECT "u"."id" FROM "users" AS "u" INNER JOIN "orders" ON "o"."user_id" = "u"."id"',
            $query->sql,
        );
    }

    public function testJoinTypeCaseNormalised(): void
    {
        $query = QueryBuilder::table('users', 'u')
            ->select('u.id')
            ->join('orders', 'o.user_id', '=', 'u.id', 'LEFT')
            ->build()
        ;

        self::assertSame(
            'SELECT "u"."id" FROM "users" AS "u" LEFT JOIN "orders" ON "o"."user_id" = "u"."id"',
            $query->sql,
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
        $query = QueryBuilder::table('users')
            ->where('age', '>', 18)
            ->orWhere('age', '<', 13)
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE "age" > ? OR "age" < ?', $query->sql);
        self::assertSame([18, 13], $query->params);
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
        $this->expectExceptionMessageIsOrContains('Use whereNull() with its negated flag instead');

        QueryBuilder::table('users')->where('deleted_at', '=', null);
    }

    public function testNullOnNonEqualityOperatorsBinds(): void
    {
        $query = QueryBuilder::table('users')->where('age', '<', null)->build();

        self::assertSame('SELECT * FROM "users" WHERE "age" < ?', $query->sql);
        self::assertSame([null], $query->params);
    }

    public function testWhereNullSpellsBothPolaritiesThroughTheNegatedFlag(): void
    {
        // The NOT-polarity travels as the same negated flag the
        // predicate fold carries -- one verb, both spellings.
        self::assertSame(
            'SELECT * FROM "users" WHERE "deleted_at" IS NULL',
            QueryBuilder::table('users')->whereNull('deleted_at')->build()->sql,
        );
        self::assertSame(
            'SELECT * FROM "users" WHERE "deleted_at" IS NOT NULL',
            QueryBuilder::table('users')->whereNull('deleted_at', negated: true)->build()->sql,
        );
    }

    public function testWhereInSpellsBothPolaritiesThroughTheNegatedFlag(): void
    {
        $plain = QueryBuilder::table('users')->whereIn('id', [1, 2, 3])->build();
        self::assertSame('SELECT * FROM "users" WHERE "id" IN (?, ?, ?)', $plain->sql);
        self::assertSame([1, 2, 3], $plain->params);

        $negated = QueryBuilder::table('users')->whereIn('id', [1, 2, 3], negated: true)->build();
        self::assertSame('SELECT * FROM "users" WHERE "id" NOT IN (?, ?, ?)', $negated->sql);
        self::assertSame([1, 2, 3], $negated->params);
    }

    public function testWhereInSubqueryMergesParamsAfterOuterParams(): void
    {
        $query = QueryBuilder::table('users', 'u')
            ->select('u.id')
            ->where('u.age', '>', 18)
            ->whereIn('u.id', QueryBuilder::table('bans')->select('user_id')->where('reason', '=', 'abuse'))
            ->build()
        ;

        self::assertSame(
            'SELECT "u"."id" FROM "users" AS "u" WHERE "u"."age" > ? AND "u"."id" IN'
            . ' (SELECT "user_id" FROM "bans" WHERE "reason" = ?)',
            $query->sql,
        );
        self::assertSame([18, 'abuse'], $query->params);
    }

    public function testWhereNotInSubqueryMergesParamsAfterOuterParams(): void
    {
        $query = QueryBuilder::table('users', 'u')
            ->select('u.id')
            ->where('u.age', '>', 18)
            ->whereIn(
                'u.id',
                QueryBuilder::table('bans')->select('user_id')->where('reason', '=', 'abuse'),
                negated: true,
            )
            ->build()
        ;

        self::assertSame(
            'SELECT "u"."id" FROM "users" AS "u" WHERE "u"."age" > ? AND "u"."id" NOT IN'
            . ' (SELECT "user_id" FROM "bans" WHERE "reason" = ?)',
            $query->sql,
        );
        self::assertSame([18, 'abuse'], $query->params);
    }

    public function testWhereInWithTheEmptyListIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('whereIn() requires a non-empty list of values.');

        QueryBuilder::table('users')->whereIn('id', []);
    }

    public function testWhereBetweenBindsLowThenHigh(): void
    {
        $query = QueryBuilder::table('users')->whereBetween('age', 13, 19)->build();

        self::assertSame('SELECT * FROM "users" WHERE "age" BETWEEN ? AND ?', $query->sql);
        self::assertSame([13, 19], $query->params);
    }

    public function testWhereBetweenRejectsNonScalars(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'whereBetween() low must be null, bool, int, float, string or SqlExpression (got array).',
        );

        QueryBuilder::table('users')->whereBetween('age', [], 19);
    }

    public function testWhereBetweenRejectsANonScalarHighBound(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'whereBetween() high must be null, bool, int, float, string or SqlExpression (got array).',
        );

        QueryBuilder::table('users')->whereBetween('age', 13, []);
    }

    public function testWhereRejectsANonScalarValue(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "condition value for 'age' must be null, bool, int, float, string or SqlExpression (got array).",
        );

        QueryBuilder::table('users')->where('age', '>', []);
    }

    public function testWhereInRejectsANonScalarMember(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'whereIn() value must be null, bool, int, float, string or SqlExpression (got array).',
        );

        QueryBuilder::table('users')->whereIn('id', [['nested']]);
    }

    public function testWhereLikeBindsThePattern(): void
    {
        $query = QueryBuilder::table('users')->whereLike('name', 'Ar%')->build();

        self::assertSame('SELECT * FROM "users" WHERE "name" LIKE ?', $query->sql);
        self::assertSame(['Ar%'], $query->params);
    }

    public function testWhereLikeEscapesTheWildcards(): void
    {
        $query = QueryBuilder::table('users')->whereLike('name', 'Ar\%ry_an%', true)->build();

        self::assertSame("SELECT * FROM \"users\" WHERE \"name\" LIKE ? ESCAPE '\\'", $query->sql);
        self::assertSame(['Ar\\\\\%ry\_an\%'], $query->params);
    }

    public function testWhereLikeWithTheEmptyPatternIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('whereLike() pattern must not be empty.');

        QueryBuilder::table('users')->whereLike('name', '');
    }

    public function testASqlExpressionColumnPassesThroughInTheCondition(): void
    {
        $query = QueryBuilder::table('users')->where(new SqlExpression('LOWER(name)'), '=', 'ari')->build();

        self::assertSame('SELECT * FROM "users" WHERE LOWER(name) = ?', $query->sql);
        self::assertSame(['ari'], $query->params);
    }

    // ------------------------------------------------------------------
    // Grouping, having, ordering, paging
    // ------------------------------------------------------------------

    public function testGroupByHavingOrderBySpellTheirClauses(): void
    {
        $query = QueryBuilder::table('users')
            ->select('city')
            ->groupBy('city')
            ->having('city', '!=', 'Jakarta')
            ->orderBy('city')
            ->build()
        ;

        self::assertSame(
            'SELECT "city" FROM "users" GROUP BY "city" HAVING "city" != ? ORDER BY "city" ASC',
            $query->sql,
        );
        self::assertSame(['Jakarta'], $query->params);
    }

    public function testGroupBySpellsEveryColumnGiven(): void
    {
        $query = QueryBuilder::table('users')->select('city')->groupBy('city', 'region')->build();

        self::assertSame('SELECT "city" FROM "users" GROUP BY "city", "region"', $query->sql);
    }

    public function testTwoHavingClausesBindInTheirCallOrder(): void
    {
        $query = QueryBuilder::table('users')
            ->select('city')
            ->groupBy('city')
            ->having('city', '!=', 'Jakarta')
            ->having('region', '=', 'Bali')
            ->build()
        ;

        self::assertSame(
            'SELECT "city" FROM "users" GROUP BY "city" HAVING "city" != ? AND "region" = ?',
            $query->sql,
        );
        self::assertSame(['Jakarta', 'Bali'], $query->params);
    }

    public function testGroupByRequiresAColumn(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('groupBy() requires at least one column.');

        QueryBuilder::table('users')->groupBy();
    }

    public function testOrderByDirectionLowercaseIsNormalised(): void
    {
        $query = QueryBuilder::table('users')->orderBy('name', 'desc')->build();

        self::assertSame('SELECT * FROM "users" ORDER BY "name" DESC', $query->sql);
    }

    public function testOrderByDirectionFoldsMultibyteCaseSpellings(): void
    {
        // The long-s spelling folds onto DESC under the character map
        // while the byte map would leave it unrecognised -- the fold's
        // own vocabulary answers the folded word.
        $query = QueryBuilder::table('users')->orderBy('name', 'deſc')->build();

        self::assertSame('SELECT * FROM "users" ORDER BY "name" DESC', $query->sql);
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
            QueryBuilder::table('users')->limit(0)->build()->sql,
        );
        self::assertSame(
            'SELECT * FROM "users" LIMIT 1 OFFSET 0',
            QueryBuilder::table('users')->limit(1, 0)->build()->sql,
        );
        self::assertSame(
            'SELECT * FROM "users" LIMIT 2',
            QueryBuilder::table('users')->limit(2)->build()->sql,
        );
    }

    public function testNegativeLimitIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('limit() must be >= 0.');

        QueryBuilder::table('users')->limit(-1);
    }

    public function testNegativeOffsetIsRefused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('offset() must be >= 0.');

        QueryBuilder::table('users')->limit(1, -1);
    }

    // ------------------------------------------------------------------
    // INSERT
    // ------------------------------------------------------------------

    public function testInsertSpellsColumnsAndValues(): void
    {
        $query = QueryBuilder::table('users')->insertRows([['name' => 'Ari', 'age' => 20]])->build();

        self::assertSame('INSERT INTO "users" ("name", "age") VALUES (?, ?)', $query->sql);
        self::assertSame(['Ari', 20], $query->params);
    }

    public function testInsertRowsSpellsEveryRow(): void
    {
        $query = QueryBuilder::table('users')->insertRows([
            ['name' => 'Ari', 'age' => 20],
            ['name' => 'Budi', 'age' => 17],
        ])->build();

        self::assertSame('INSERT INTO "users" ("name", "age") VALUES (?, ?), (?, ?)', $query->sql);
        self::assertSame(['Ari', 20, 'Budi', 17], $query->params);
    }

    public function testInsertExpressionValuesAreEmbeddedVerbatim(): void
    {
        $query = QueryBuilder::table('users')
            ->insertRows([['name' => 'Ari', 'created_at' => new SqlExpression('NOW()')]])
            ->build()
        ;

        self::assertSame('INSERT INTO "users" ("name", "created_at") VALUES (?, NOW())', $query->sql);
        self::assertSame(['Ari'], $query->params);
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

    public function testInsertRowsValidatesEveryColumnNameAgainstTheGrammar(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid column 'na me'.");

        QueryBuilder::table('users')->insertRows([['na me' => 'Ari']]);
    }

    // ------------------------------------------------------------------
    // UPDATE / DELETE
    // ------------------------------------------------------------------

    public function testUpdateSpellsTheSetPairsThenWhereParams(): void
    {
        $query = QueryBuilder::table('users')
            ->update(['name' => 'Ari', 'age' => 21])
            ->where('id', '=', 1)
            ->build()
        ;

        self::assertSame('UPDATE "users" SET "name" = ?, "age" = ? WHERE "id" = ?', $query->sql);
        self::assertSame(['Ari', 21, 1], $query->params);
    }

    public function testUpdateExpressionValuesAreEmbeddedVerbatim(): void
    {
        $query = QueryBuilder::table('users')
            ->update(['seen_at' => new SqlExpression('NOW()')])
            ->where('id', '=', 1)
            ->build()
        ;

        self::assertSame('UPDATE "users" SET "seen_at" = NOW() WHERE "id" = ?', $query->sql);
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

    public function testUpdateValidatesEveryColumnNameAgainstTheGrammar(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Invalid column 'na me'.");

        QueryBuilder::table('users')->update(['na me' => 'Ari']);
    }

    public function testDeleteSpellsTheWhereSuffix(): void
    {
        $query = QueryBuilder::table('users')->delete()->where('id', '=', 1)->build();

        self::assertSame('DELETE FROM "users" WHERE "id" = ?', $query->sql);
        self::assertSame([1], $query->params);
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

    public function testTheBuildDispatchRefusesThePhaseTheBuilderNeverLetsThrough(): void
    {
        // The facade's phase vocabulary is closed by QueryPhase, so the
        // dispatch's own default arm is only reachable by calling the
        // fold directly -- the refusal is pinned where it lives.
        $sqlIdentifier = new SqlIdentifier();
        $querySnapshot = new QuerySnapshot(
            'users',
            null,
            ['*'],
            false,
            [],
            [],
            new QueryHaving($sqlIdentifier),
            [],
            null,
            null,
            [],
            [],
            false,
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("Unknown query type 'bogus'.");

        QueryBuild::statement('bogus', $querySnapshot, new QueryWhere($sqlIdentifier));
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
            ->build()->sql
        ;

        self::assertSame($build(), $build());
    }

    /**
     * @param callable(QueryBuilder): QueryBuilder $verb
     */
    #[DataProvider('provideEverySelectSideVerbRefusesTheWritePhasesCases')]
    public function testEverySelectSideVerbRefusesTheWritePhases(string $method, callable $verb): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains("{$method} is not available on a delete query.");

        $verb(QueryBuilder::table('users')->delete());
    }

    // ------------------------------------------------------------------
    // Phase guards, every verb
    // ------------------------------------------------------------------

    /**
     * @return iterable<non-empty-string, array{non-empty-string, callable(QueryBuilder): QueryBuilder}>
     */
    public static function provideEverySelectSideVerbRefusesTheWritePhasesCases(): iterable
    {
        yield 'select' => [
            'select()',
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->select('id'),
        ];

        yield 'distinct' => [
            'distinct()',
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->distinct(),
        ];

        yield 'join' => [
            'join()',
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->join('o', 'o.i', '=', 'u.i'),
        ];

        yield 'groupBy' => [
            'groupBy()',
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->groupBy('city'),
        ];

        yield 'having' => [
            'having()',
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->having('city', '!=', 'x'),
        ];

        yield 'orderBy' => [
            'orderBy()',
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->orderBy('name'),
        ];

        yield 'limit' => ['limit()', static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->limit(1)];

        yield 'limitWithOffset' => [
            'limit()',
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->limit(1, 1),
        ];
    }

    /**
     * @param callable(QueryBuilder): QueryBuilder $verb
     */
    #[DataProvider('provideEveryWhereVerbRefusesTheInsertPhaseCases')]
    public function testEveryWhereVerbRefusesTheInsertPhase(callable $verb): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('WHERE clauses are not available on an insert query.');

        $verb(QueryBuilder::table('users')->insertRows([['name' => 'Ari']]));
    }

    /**
     * @return iterable<non-empty-string, array{callable(QueryBuilder): QueryBuilder}>
     */
    public static function provideEveryWhereVerbRefusesTheInsertPhaseCases(): iterable
    {
        yield 'where' => [static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->where('id', '=', 1)];

        yield 'orWhere' => [
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->orWhere('id', '=', 1),
        ];

        yield 'whereIn' => [
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereIn('id', [1]),
        ];

        yield 'whereNegatedIn' => [
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereIn('id', [1], negated: true),
        ];

        yield 'whereNull' => [
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereNull('gone'),
        ];

        yield 'whereNegatedNull' => [
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereNull('gone', negated: true),
        ];

        yield 'whereBetween' => [
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereBetween('age', 1, 2),
        ];

        yield 'whereLike' => [
            static fn (QueryBuilder $queryBuilder): QueryBuilder => $queryBuilder->whereLike('name', 'A%'),
        ];
    }
}
