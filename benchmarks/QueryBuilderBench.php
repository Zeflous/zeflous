<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;

/**
 * The database lane's query-build reference subject -- the pure
 * construction economics the persistence phase instruments for
 * regression watching (the CI Bench lane runs this file on every
 * push).
 *
 * Not a victory claim. The honest cost structure these numbers pin:
 * the identifier grammar's validation pass over every column and
 * the where family's fragment assembly, measured over a select
 * with three conditions and over the write shapes whose guards
 * carry the same validation burden. No statement is executed and
 * no connection is opened: the builder's cost is its own number,
 * separate from any driver round-trip the adapters lane owns.
 *
 * The bench mirrors `TelemetrySpanBench`'s discipline: each method
 * guards its own invariant, so a broken grammar fails the lane
 * rather than silently reporting a number.
 */
final readonly class QueryBuilderBench
{
    /**
     * @return array{string, list<mixed>}
     */
    #[Revs(20000)]
    public function benchSelectWithThreeConditions(): array
    {
        $sqlQuery = QueryBuilder::table('users', 'u')
            ->select('u.id', 'u.name', 'u.email')
            ->where('u.age', '>', 18)
            ->where('u.status', '=', 'active')
            ->orWhere('u.role', '=', 'admin')
            ->orderBy('u.name', 'ASC')
            ->limit(25)
            ->build()
        ;

        \assert($sqlQuery->sql !== '');
        \assert($sqlQuery->params === [18, 'active', 'admin']);

        return [$sqlQuery->sql, $sqlQuery->params];
    }

    /**
     * @return array{string, list<mixed>}
     */
    #[Revs(20000)]
    public function benchInsertBuild(): array
    {
        $sqlQuery = QueryBuilder::table('users')
            ->insertRows([['name' => 'Ari', 'age' => 20, 'status' => 'active']])
            ->build()
        ;

        \assert($sqlQuery->sql !== '');
        \assert($sqlQuery->params === ['Ari', 20, 'active']);

        return [$sqlQuery->sql, $sqlQuery->params];
    }

    /**
     * @return array{string, list<mixed>}
     */
    #[Revs(20000)]
    public function benchUpdateWithWhereBuild(): array
    {
        $sqlQuery = QueryBuilder::table('users')
            ->update(['name' => 'Ari', 'seen_at' => 123])
            ->where('id', '=', 1)
            ->build()
        ;

        \assert($sqlQuery->sql !== '');
        \assert($sqlQuery->params === ['Ari', 123, 1]);
        \assert($sqlQuery->unbounded === false);

        return [$sqlQuery->sql, $sqlQuery->params];
    }

    /**
     * The backtick lane's own subject: the same select over the
     * MySQL vocabulary (issue #778), the LIKE escape's pipe dialect
     * included -- the grammar's validation pass costs the same over
     * either quoting, and this pin holds the lane to its own
     * spelling so a future drift of the vocabulary's rendering
     * fails the lane rather than silently reporting a number.
     *
     * @return array{string, list<mixed>}
     */
    #[Revs(20000)]
    public function benchMysqlQuotedSelectWithLike(): array
    {
        $sqlQuery = QueryBuilder::table('users', 'u', IdentifierQuoting::MySql)
            ->select('u.id')
            ->whereLike('u.name', 'Ari|%', true)
            ->limit(1)
            ->build()
        ;

        \assert(str_contains($sqlQuery->sql, '`users` AS `u`'));
        \assert(str_contains($sqlQuery->sql, "`u`.`name` LIKE ? ESCAPE '|'"));
        \assert($sqlQuery->params === ['Ari|||%']);

        return [$sqlQuery->sql, $sqlQuery->params];
    }
}
