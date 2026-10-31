<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The having clause's state fold: the aggregate-filter fragments and
 * their bound values, kept SEPARATE from the where family's params so
 * the compiled statement binds in textual order -- every WHERE value
 * before every HAVING value -- no matter which verb the caller spelled
 * first (the call-order binding the reference's single shared param
 * list could not promise).
 *
 * @internal
 */
final class QueryHaving
{
    /** @var list<string> */
    private array $fragments = [];

    /** @var list<mixed> */
    private array $params = [];

    public function __construct(
        private readonly SqlIdentifier $sqlIdentifier,
    ) {
    }

    /**
     * Renders and records one aggregate-filter condition.
     */
    public function comparison(SqlExpression|string $column, string $operator, mixed $value): void
    {
        $queryFragment = QueryPredicate::comparison($this->sqlIdentifier, $column, $operator, $value);
        $this->fragments[] = $queryFragment->fragment;
        $this->params = [...$this->params, ...$queryFragment->params];
    }

    /**
     * True when no condition lives here yet.
     */
    public function isEmpty(): bool
    {
        return $this->fragments === [];
    }

    /**
     * @return list<mixed>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * The rendered ` HAVING ...` suffix, empty when no condition lives
     * here yet.
     */
    public function suffix(): string
    {
        if ($this->fragments === []) {
            return '';
        }

        return ' HAVING ' . implode(' AND ', $this->fragments);
    }
}
