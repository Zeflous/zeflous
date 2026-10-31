<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * Fluent, driver-agnostic SQL query builder producing {@see SqlQuery}
 * value objects with strictly ordered positional parameters.
 *
 * Design rules:
 * - every identifier (table, column, alias) is validated against the
 *   {@see SqlIdentifier} grammar and double-quoted -- user input can
 *   never become SQL syntax;
 * - string column arguments must be plain identifiers (optionally
 *   `table.column` or `identifier AS alias`); anything dynamic is passed
 *   as an explicit {@see SqlExpression} so raw SQL stays greppable;
 * - all values become `?` placeholders; null/bool/int/float/string and
 *   SqlExpression are accepted, everything else is rejected;
 * - UPDATE/DELETE without WHERE must call {@see allowUnbounded()} first --
 *   accidental full-table writes are a bug, not a feature;
 * - output is deterministic: same builder state, same SQL string.
 *
 * The verb surface is deliberately lean (the roadmap's own "query
 * builder sederhana"): the sugar forms the reference carried --
 * selectRaw, aggregate beyond COUNT, leftJoin, single-row insert,
 * whereColumn and whereNested -- are expressible through the
 * SqlExpression escape hatch ({@see SqlExpression}), the join type
 * parameter, the one-row insertRows list, or await the condition-object
 * API the Domain-Driven direction eventually wants; keeping the facade
 * small is what keeps it under the architecture ceiling.
 *
 * The builder is the fluent facade over the lane's folds: the phase
 * guards in {@see QueryPhase}, the predicate grammar in
 * {@see QueryPredicate} over the shared {@see QueryValues} vocabulary,
 * the fragment state in {@see QueryWhere}, the column-list renderings
 * in {@see QueryColumns}, the paging guards in {@see QueryPaging}, the
 * aggregate sugar in {@see QueryAggregate}, the join and order spellings
 * in {@see QueryJoin} / {@see QueryOrder}, the write-payload validation
 * in {@see QueryRows}, and the statement assembly in
 * {@see QuerySelectAssembly} / {@see QueryWriteAssembly} over the
 * {@see QuerySnapshot} this facade collects -- each fold owns one
 * question, the facade owns the verbs.
 */
final class QueryBuilder
{
    private readonly SqlIdentifier $sqlIdentifier;

    private readonly QueryWhere $queryWhere;

    private readonly QueryHaving $queryHaving;

    private string $type = QueryPhase::SELECT;

    private ?string $table = null;

    private ?string $tableAlias = null;

    private bool $distinct = false;

    private bool $unboundedAllowed = false;

    /** @var list<string> */
    private array $selectColumns = [];

    /** @var list<string> */
    private array $joins = [];

    /** @var list<string> */
    private array $groupBy = [];

    /** @var list<string> */
    private array $orderBy = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @var list<array<string, mixed>> */
    private array $insertRows = [];

    /** @var array<string, mixed> */
    private array $updatePairs = [];

    public function __construct()
    {
        $this->sqlIdentifier = new SqlIdentifier();
        $this->queryWhere = new QueryWhere($this->sqlIdentifier);
        $this->queryHaving = new QueryHaving($this->sqlIdentifier);
    }

    public static function table(SqlExpression|string $table, ?string $alias = null): self
    {
        $qb = new self();
        $qb->table = $qb->sqlIdentifier->table($table);
        $qb->tableAlias = $alias;

        return $qb;
    }

    // ------------------------------------------------------------------
    // Select-side verbs
    // ------------------------------------------------------------------

    public function select(SqlExpression|string ...$columns): self
    {
        QueryPhase::assertSelect($this->type, 'select()');
        $this->selectColumns = QueryColumns::select($this->sqlIdentifier, $columns, 'select()');

        return $this;
    }

    public function distinct(bool $on = true): self
    {
        QueryPhase::assertSelect($this->type, 'distinct()');
        $this->distinct = $on;

        return $this;
    }

    /**
     * Aggregate sugar producing `SELECT COUNT(col) AS aggregate FROM ...`;
     * every other aggregate travels as an explicit SqlExpression.
     */
    public function count(string $column = '*'): self
    {
        return $this->select(QueryAggregate::expression($this->sqlIdentifier, 'COUNT', $column));
    }

    /**
     * @param string $type one of inner, left, right, cross
     */
    public function join(
        SqlExpression|string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'inner',
    ): self {
        QueryPhase::assertSelect($this->type, 'join()');
        $this->joins[] = QueryJoin::render($this->sqlIdentifier, $table, $first, $operator, $second, $type);

        return $this;
    }

    public function groupBy(SqlExpression|string ...$columns): self
    {
        QueryPhase::assertSelect($this->type, 'groupBy()');
        $this->groupBy = QueryColumns::groupBy($this->sqlIdentifier, $columns);

        return $this;
    }

    public function having(SqlExpression|string $column, string $operator, mixed $value): self
    {
        QueryPhase::assertSelect($this->type, 'having()');
        $this->queryHaving->comparison($column, $operator, $value);

        return $this;
    }

    public function orderBy(SqlExpression|string $column, string $direction = 'ASC'): self
    {
        QueryPhase::assertSelect($this->type, 'orderBy()');
        $this->orderBy[] = QueryOrder::render($this->sqlIdentifier, $column, $direction);

        return $this;
    }

    public function limit(int $limit): self
    {
        QueryPhase::assertSelect($this->type, 'limit()');
        $this->limit = QueryPaging::limit($limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        QueryPhase::assertSelect($this->type, 'offset()');
        $this->offset = QueryPaging::offset($offset, $this->limit);

        return $this;
    }

    // ------------------------------------------------------------------
    // Where-family verbs
    // ------------------------------------------------------------------

    /**
     * AND condition. Two-argument form is rejected: equality is spelled
     * `where('col', '=', $value)` and NULL checks use whereNull().
     */
    public function where(SqlExpression|string $column, string $operator, mixed $value): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->condition($column, $operator, $value, 'AND');

        return $this;
    }

    /** OR condition against the previous condition. */
    public function orWhere(SqlExpression|string $column, string $operator, mixed $value): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->condition($column, $operator, $value, 'OR');

        return $this;
    }

    /**
     * @param list<mixed>|QueryBuilder $values
     */
    public function whereIn(string $column, array|self $values): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->inList($column, $values, false);

        return $this;
    }

    /**
     * @param list<mixed>|QueryBuilder $values
     */
    public function whereNotIn(string $column, array|self $values): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->inList($column, $values, true);

        return $this;
    }

    public function whereNull(string $column): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->nullPredicate($column, false);

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->nullPredicate($column, true);

        return $this;
    }

    public function whereBetween(string $column, mixed $low, mixed $high): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->between($column, $low, $high);

        return $this;
    }

    /**
     * @param bool $escapeWildcards when true, % _ and \ in $pattern are
     *                              escaped and the ESCAPE clause is emitted
     */
    public function whereLike(string $column, string $pattern, bool $escapeWildcards = false): self
    {
        QueryPhase::assertWhereAllowed($this->type);
        $this->queryWhere->like($column, $pattern, $escapeWildcards);

        return $this;
    }

    // ------------------------------------------------------------------
    // Write verbs
    // ------------------------------------------------------------------

    /**
     * Insert payload (single row or many); every row MUST carry the same
     * column set, and a later call replaces the earlier rows.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function insertRows(array $rows): self
    {
        $this->type = QueryPhase::marked($this->type, QueryPhase::INSERT, 'insertRows()');
        $this->insertRows = QueryInsertRows::accept($this->sqlIdentifier, $rows);

        return $this;
    }

    /**
     * @param array<string, mixed> $pairs
     */
    public function update(array $pairs): self
    {
        $this->type = QueryPhase::marked($this->type, QueryPhase::UPDATE, 'update()');
        $this->updatePairs = QueryUpdatePairs::accept($this->sqlIdentifier, $pairs);

        return $this;
    }

    public function delete(): self
    {
        $this->type = QueryPhase::marked($this->type, QueryPhase::DELETE, 'delete()');

        return $this;
    }

    /**
     * Escape hatch for UPDATE/DELETE without WHERE (truncate-style ops).
     */
    public function allowUnbounded(bool $on = true): self
    {
        $this->unboundedAllowed = $on;

        return $this;
    }

    // ------------------------------------------------------------------
    // Build
    // ------------------------------------------------------------------

    public function build(): SqlQuery
    {
        return QueryBuild::statement(
            $this->type,
            new QuerySnapshot(
                $this->table,
                $this->tableAlias,
                $this->selectColumns,
                $this->distinct,
                $this->joins,
                $this->groupBy,
                $this->queryHaving,
                $this->orderBy,
                $this->limit,
                $this->offset,
                $this->insertRows,
                $this->updatePairs,
                $this->unboundedAllowed,
            ),
            $this->queryWhere,
        );
    }

    public function toSql(): string
    {
        return $this->build()->sql;
    }

    /**
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        return $this->build()->params;
    }
}
