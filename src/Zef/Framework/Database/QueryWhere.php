<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The where-family's verb fold: each facade verb renders its predicate
 * through {@see QueryPredicate} and records the fragment on the
 * {@see QueryWhereState} the compilers read -- AND the default
 * connector, OR the one alternative.
 *
 * @internal
 */
final readonly class QueryWhere
{
    private QueryWhereState $queryWhereState;

    public function __construct(
        private SqlIdentifier $sqlIdentifier,
    ) {
        $this->queryWhereState = new QueryWhereState();
    }

    /**
     * Renders and records one comparison, ANDed or ORed with the chain.
     */
    public function condition(SqlExpression|string $column, string $operator, mixed $value, string $connector): void
    {
        $queryFragment = QueryPredicate::comparison($this->sqlIdentifier, $column, $operator, $value);
        $this->queryWhereState->add($queryFragment->fragment, $queryFragment->params, $connector);
    }

    /**
     * Renders and records one IN list, negated or not.
     *
     * @param list<mixed>|QueryBuilder $values
     */
    public function inList(string $column, array|QueryBuilder $values, bool $negated): void
    {
        $queryFragment = QueryPredicate::inList($this->sqlIdentifier, $column, $values, $negated);
        $this->queryWhereState->add($queryFragment->fragment, $queryFragment->params);
    }

    /**
     * Renders and records the NULL predicate, negated or not.
     */
    public function nullPredicate(string $column, bool $negated): void
    {
        $suffix = $negated ? ' IS NOT NULL' : ' IS NULL';
        $this->queryWhereState->add($this->sqlIdentifier->columnPath($column) . $suffix, []);
    }

    /**
     * Renders and records the BETWEEN pair.
     */
    public function between(string $column, mixed $low, mixed $high): void
    {
        $queryFragment = QueryPredicate::between($this->sqlIdentifier, $column, $low, $high);
        $this->queryWhereState->add($queryFragment->fragment, $queryFragment->params);
    }

    /**
     * Renders and records the LIKE pattern.
     */
    public function like(string $column, string $pattern, bool $escapeWildcards): void
    {
        $queryFragment = QueryPredicate::like($this->sqlIdentifier, $column, $pattern, $escapeWildcards);
        $this->queryWhereState->add($queryFragment->fragment, $queryFragment->params);
    }

    /**
     * The rendered ` WHERE ...` suffix, empty when no condition lives
     * here yet.
     */
    public function suffix(): string
    {
        return $this->queryWhereState->suffix();
    }

    /**
     * @return list<mixed>
     */
    public function params(): array
    {
        return $this->queryWhereState->params();
    }

    /**
     * True when no condition lives here yet.
     */
    public function isEmpty(): bool
    {
        return $this->queryWhereState->isEmpty();
    }
}
