<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\Condition;
use Zef\Framework\Database\ConditionBetween;
use Zef\Framework\Database\ConditionColumnComparison;
use Zef\Framework\Database\ConditionComparison;
use Zef\Framework\Database\ConditionGroup;
use Zef\Framework\Database\ConditionIn;
use Zef\Framework\Database\ConditionLike;
use Zef\Framework\Database\ConditionNot;
use Zef\Framework\Database\ConditionNull;
use Zef\Framework\Database\DatabaseException;
use Zef\Framework\Database\QueryBuilder;
use Zef\Framework\Database\QueryComparison;
use Zef\Framework\Database\QueryException;
use Zef\Framework\Database\QueryFragment;
use Zef\Framework\Database\QueryOperand;
use Zef\Framework\Database\QueryPredicate;
use Zef\Framework\Database\QueryWhere;
use Zef\Framework\Database\SqlExpression;
use Zef\Framework\Database\SqlIdentifier;
use Zef\Framework\Database\SqlQuery;

/**
 * The condition-object family's adversarial curriculum: every leaf's
 * rendering exactness (SQL string AND binding order) through the
 * builder's widened where verbs, the composites' parenthesisation
 * discipline (the group joined by its own connector, the negation
 * ALWAYS wrapped), the structural refusals by construction (the empty
 * group, the both-spelled where call), the grammar firing at the
 * composition verb exactly where the fluent verbs' own grammar fires,
 * and determinism -- the same tree always answers the same SQL.
 *
 * @internal
 */
#[CoversClass(Condition::class)]
#[CoversClass(ConditionComparison::class)]
#[CoversClass(ConditionColumnComparison::class)]
#[CoversClass(ConditionIn::class)]
#[CoversClass(ConditionNull::class)]
#[CoversClass(ConditionBetween::class)]
#[CoversClass(ConditionLike::class)]
#[CoversClass(ConditionGroup::class)]
#[CoversClass(ConditionNot::class)]
#[CoversClass(QueryBuilder::class)]
#[CoversClass(QueryWhere::class)]
#[CoversClass(QueryComparison::class)]
#[CoversClass(QueryOperand::class)]
#[CoversClass(QueryPredicate::class)]
#[CoversClass(QueryFragment::class)]
#[CoversClass(SqlIdentifier::class)]
#[CoversClass(SqlExpression::class)]
#[CoversClass(QueryException::class)]
#[CoversClass(DatabaseException::class)]
#[CoversClass(SqlQuery::class)]
final class ConditionTest extends TestCase
{
    // ------------------------------------------------------------------
    // The leaves -- rendering exactness through the widened where verbs
    // ------------------------------------------------------------------

    public function testTheComparisonLeafRendersThroughTheBuilder(): void
    {
        $query = QueryBuilder::table('users')
            ->where(new ConditionComparison('age', '>', 18))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE "age" > ?', $query->sql);
        self::assertSame([18], $query->params);
    }

    public function testTheComparisonLeafAcceptsAnExpressionColumn(): void
    {
        $query = QueryBuilder::table('users')
            ->where(new ConditionComparison(new SqlExpression('LOWER(name)'), '=', 'ari'))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE LOWER(name) = ?', $query->sql);
        self::assertSame(['ari'], $query->params);
    }

    public function testTheColumnComparisonLeafRendersBothPathsQuoted(): void
    {
        $query = QueryBuilder::table('bookings')
            ->where(new ConditionColumnComparison('ended_at', '>', 'started_at'))
            ->build()
        ;

        self::assertSame('SELECT * FROM "bookings" WHERE "ended_at" > "started_at"', $query->sql);
        self::assertSame([], $query->params);
    }

    public function testTheColumnComparisonLeafGuardsItsOperatorAtComposition(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            "Unknown comparison operator 'like' (allowed: = != <> < > <= >=).",
        );

        QueryBuilder::table('bookings')->where(new ConditionColumnComparison('ended_at', 'like', 'started_at'));
    }

    public function testTheInLeafRendersTheListAndBindsInOrder(): void
    {
        $query = QueryBuilder::table('users')
            ->where(new ConditionIn('status', ['open', 'held']))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE "status" IN (?, ?)', $query->sql);
        self::assertSame(['open', 'held'], $query->params);
    }

    public function testTheInLeafCarriesTheNotPolarity(): void
    {
        $query = QueryBuilder::table('users')
            ->where(new ConditionIn('status', ['open', 'held'], negated: true))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE "status" NOT IN (?, ?)', $query->sql);
        self::assertSame(['open', 'held'], $query->params);
    }

    public function testTheInLeafAcceptsASubqueryBuilder(): void
    {
        $subquery = QueryBuilder::table('roles')->select('id')->where('active', '=', 1);

        $query = QueryBuilder::table('users')
            ->where('tenant', '=', 9)
            ->where(new ConditionIn('role_id', $subquery))
            ->build()
        ;

        self::assertSame(
            'SELECT * FROM "users" WHERE "tenant" = ? AND "role_id" IN (SELECT "id" FROM "roles" WHERE "active" = ?)',
            $query->sql,
        );
        self::assertSame([9, 1], $query->params);
    }

    public function testTheInLeafRefusesTheEmptyListAtComposition(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('whereIn() requires a non-empty list of values.');

        QueryBuilder::table('users')->where(new ConditionIn('status', []));
    }

    public function testTheNullLeafRendersBothPolarities(): void
    {
        $isNull = QueryBuilder::table('users')->where(new ConditionNull('deleted_at'))->build();
        $isNotNull = QueryBuilder::table('users')->where(new ConditionNull('deleted_at', negated: true))->build();

        self::assertSame('SELECT * FROM "users" WHERE "deleted_at" IS NULL', $isNull->sql);
        self::assertSame([], $isNull->params);
        self::assertSame('SELECT * FROM "users" WHERE "deleted_at" IS NOT NULL', $isNotNull->sql);
    }

    public function testTheBetweenLeafBindsLowThenHigh(): void
    {
        $query = QueryBuilder::table('users')
            ->where(new ConditionBetween('age', 13, 19))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE "age" BETWEEN ? AND ?', $query->sql);
        self::assertSame([13, 19], $query->params);
    }

    public function testTheLikeLeafBindsThePatternAndEscapesWhenAsked(): void
    {
        $bare = QueryBuilder::table('users')->where(new ConditionLike('name', 'Ar%'))->build();
        $escaped = QueryBuilder::table('users')
            ->where(new ConditionLike('name', 'Ar%', escapeWildcards: true))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE "name" LIKE ?', $bare->sql);
        self::assertSame(['Ar%'], $bare->params);
        self::assertSame("SELECT * FROM \"users\" WHERE \"name\" LIKE ? ESCAPE '\\'", $escaped->sql);
        self::assertSame(['Ar\%'], $escaped->params);
    }

    // ------------------------------------------------------------------
    // The composites -- the group and the negation
    // ------------------------------------------------------------------

    public function testTheAllGroupRendersParenthesisedAndAndJoined(): void
    {
        $query = QueryBuilder::table('users')
            ->where(ConditionGroup::all(
                new ConditionComparison('status', '=', 'open'),
                new ConditionComparison('age', '>', 18),
            ))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE ("status" = ? AND "age" > ?)', $query->sql);
        self::assertSame(['open', 18], $query->params);
    }

    public function testTheAnyGroupRendersParenthesisedAndOrJoined(): void
    {
        $query = QueryBuilder::table('users')
            ->where(ConditionGroup::any(
                new ConditionComparison('name', '=', 'Ari'),
                new ConditionComparison('name', '=', 'Budi'),
            ))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE ("name" = ? OR "name" = ?)', $query->sql);
        self::assertSame(['Ari', 'Budi'], $query->params);
    }

    public function testGroupsNestWithoutCallbacks(): void
    {
        // whereNested's group form, returned as a value: a group inside
        // a group, composed before the query and applied once.
        $query = QueryBuilder::table('users')
            ->where(ConditionGroup::all(
                new ConditionComparison('active', '=', 1),
                ConditionGroup::any(
                    new ConditionComparison('age', '<', 18),
                    new ConditionComparison('age', '>', 65),
                ),
            ))
            ->build()
        ;

        self::assertSame(
            'SELECT * FROM "users" WHERE ("active" = ? AND ("age" < ? OR "age" > ?))',
            $query->sql,
        );
        self::assertSame([1, 18, 65], $query->params);
    }

    public function testTheNegationAlwaysWrapsItsOperand(): void
    {
        $overLeaf = QueryBuilder::table('users')
            ->where(new ConditionNot(new ConditionNull('deleted_at')))
            ->build()
        ;
        $overGroup = QueryBuilder::table('users')
            ->where(new ConditionNot(ConditionGroup::any(
                new ConditionComparison('age', '<', 18),
                new ConditionComparison('age', '>', 65),
            )))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE NOT ("deleted_at" IS NULL)', $overLeaf->sql);
        self::assertSame([], $overLeaf->params);
        self::assertSame('SELECT * FROM "users" WHERE NOT (("age" < ? OR "age" > ?))', $overGroup->sql);
        self::assertSame([18, 65], $overGroup->params);
    }

    public function testTheMixedChainJoinsTheTreeWithTheFluentPredicates(): void
    {
        $query = QueryBuilder::table('users')
            ->where('tenant', '=', 9)
            ->where(ConditionGroup::any(
                new ConditionComparison('name', '=', 'Ari'),
                new ConditionComparison('name', '=', 'Budi'),
            ))
            ->where('active', '=', 1)
            ->orWhere(new ConditionNull('deleted_at'))
            ->build()
        ;

        self::assertSame(
            'SELECT * FROM "users" WHERE "tenant" = ? '
            . 'AND ("name" = ? OR "name" = ?) AND "active" = ? OR "deleted_at" IS NULL',
            $query->sql,
        );
        self::assertSame([9, 'Ari', 'Budi', 1], $query->params);
    }

    public function testOrWhereTreesORWithTheChain(): void
    {
        $query = QueryBuilder::table('users')
            ->where('age', '>', 65)
            ->orWhere(new ConditionIn('status', ['retired', 'emeritus']))
            ->build()
        ;

        self::assertSame('SELECT * FROM "users" WHERE "age" > ? OR "status" IN (?, ?)', $query->sql);
        self::assertSame([65, 'retired', 'emeritus'], $query->params);
    }

    public function testDeepTreesBindInTheConstructionOrder(): void
    {
        $query = QueryBuilder::table('users')
            ->where(ConditionGroup::all(
                ConditionGroup::any(
                    new ConditionBetween('age', 13, 19),
                    new ConditionBetween('age', 65, 90),
                ),
                new ConditionNot(new ConditionNull('email')),
            ))
            ->build()
        ;

        self::assertSame(
            'SELECT * FROM "users" WHERE (("age" BETWEEN ? AND ? OR "age" BETWEEN ? AND ?) AND NOT ("email" IS NULL))',
            $query->sql,
        );
        self::assertSame([13, 19, 65, 90], $query->params);
    }

    // ------------------------------------------------------------------
    // The structural refusals -- by construction, not by rendering
    // ------------------------------------------------------------------

    /**
     * @param callable(): Condition $constructor
     */
    #[DataProvider('provideTheGroupRefusesZeroMembersAtConstructionCases')]
    public function testTheGroupRefusesZeroMembersAtConstruction(callable $constructor): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains('requires at least one condition.');

        $constructor();
    }

    /**
     * @return iterable<string, array{callable(): Condition}>
     */
    public static function provideTheGroupRefusesZeroMembersAtConstructionCases(): iterable
    {
        yield 'all' => [static fn (): Condition => ConditionGroup::all()];

        yield 'any' => [static fn (): Condition => ConditionGroup::any()];
    }

    public function testWhereRefusesTheObjectAndTheTripleTogether(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'where() takes one Condition object or the column/operator/value triple, not both.',
        );

        QueryBuilder::table('users')->where(new ConditionNull('gone'), '=');
    }

    public function testWhereRefusesTheObjectWithAStrayValue(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'where() takes one Condition object or the column/operator/value triple, not both.',
        );

        QueryBuilder::table('users')->where(new ConditionNull('gone'), null, 5);
    }

    public function testOrWhereRefusesTheObjectAndTheTripleTogether(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'orWhere() takes one Condition object or the column/operator/value triple, not both.',
        );

        QueryBuilder::table('users')->orWhere(new ConditionNull('gone'), '=');
    }

    // ------------------------------------------------------------------
    // The grammar -- firing at the composition verb, one spelling
    // ------------------------------------------------------------------

    public function testTheLeafsGrammarFiresAtTheCompositionVerb(): void
    {
        // A leaf built with a bad operator surfaces exactly where the
        // fluent verb's own bad operator surfaces: at the where call.
        try {
            QueryBuilder::table('users')->where(new ConditionComparison('age', '~~', 5));
            self::fail("The comparison leaf's operator guard did not fire.");
        } catch (QueryException $queryException) {
            self::assertStringContainsString("Unknown comparison operator '~~'", $queryException->getMessage());
        }

        try {
            QueryBuilder::table('users')->where(new ConditionComparison('age', '>', []));
            self::fail("The comparison leaf's scalar guard did not fire.");
        } catch (QueryException $queryException) {
            self::assertStringContainsString(
                "condition value for 'age' must be null, bool, int, float, string or SqlExpression (got array)",
                $queryException->getMessage(),
            );
        }

        try {
            QueryBuilder::table('users')->where(new ConditionLike('name', ''));
            self::fail("The like leaf's pattern guard did not fire.");
        } catch (QueryException $queryException) {
            self::assertStringContainsString('whereLike() pattern must not be empty.', $queryException->getMessage());
        }
    }

    public function testWhereRefusesTheColumnFormWithoutItsOperator(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'where() requires an operator for the column form (equality is spelled where(\'col\', \'=\', $value)).',
        );

        QueryBuilder::table('users')->where('age');
    }

    public function testOrWhereRefusesTheColumnFormWithoutItsOperator(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageIsOrContains(
            'orWhere() requires an operator for the column form (equality is spelled orWhere(\'col\', \'=\', $value)).',
        );

        QueryBuilder::table('users')->orWhere('age');
    }

    public function testTheWritePhaseGuardStillBitesOnTrees(): void
    {
        $this->expectException(QueryException::class);

        QueryBuilder::table('users')->insertRows([['name' => 'Ari']])->where(new ConditionNull('gone'));
    }

    // ------------------------------------------------------------------
    // The direct contract -- the fragment spelling pinned at the fold
    // ------------------------------------------------------------------

    public function testTheFragmentsDirectContractPinsTheSpelling(): void
    {
        $sqlIdentifier = new SqlIdentifier();

        $group = ConditionGroup::all(
            new ConditionComparison('a', '=', 1),
            ConditionGroup::any(new ConditionNull('b'), new ConditionNull('c')),
        )->fragment($sqlIdentifier);

        self::assertSame('("a" = ? AND ("b" IS NULL OR "c" IS NULL))', $group->fragment);
        self::assertSame([1], $group->params);

        $negation = new ConditionNot(new ConditionComparison('a', '=', 1))->fragment($sqlIdentifier);

        self::assertSame('NOT ("a" = ?)', $negation->fragment);
        self::assertSame([1], $negation->params);

        $columnComparison = new ConditionColumnComparison('u.id', '=', 'r.user_id')->fragment($sqlIdentifier);

        self::assertSame('"u"."id" = "r"."user_id"', $columnComparison->fragment);
        self::assertSame([], $columnComparison->params);
    }

    // ------------------------------------------------------------------
    // Determinism -- the same tree, the same answer
    // ------------------------------------------------------------------

    public function testTheSameTreeAnswersTheSameSql(): void
    {
        $tree = static fn (): Condition => ConditionGroup::all(
            new ConditionComparison('active', '=', 1),
            ConditionGroup::any(
                new ConditionIn('status', ['open', 'held']),
                new ConditionNot(new ConditionNull('seen_at')),
            ),
        );

        $first = QueryBuilder::table('events')->where($tree())->build();
        $second = QueryBuilder::table('events')->where($tree())->build();

        self::assertSame($first->sql, $second->sql);
        self::assertSame($first->params, $second->params);
        self::assertSame(
            'SELECT * FROM "events" WHERE ("active" = ? AND ("status" IN (?, ?) OR NOT ("seen_at" IS NULL)))',
            $second->sql,
        );
    }
}
