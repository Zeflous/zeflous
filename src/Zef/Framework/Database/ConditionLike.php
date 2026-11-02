<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The LIKE leaf: one `column LIKE ?` predicate as a value object,
 * with the wildcard-escaping discipline the fluent verb carries --
 * escaped only when the caller asks, the ESCAPE clause emitted beside.
 */
final readonly class ConditionLike implements Condition
{
    /**
     * @param bool $escapeWildcards when true, % _ and \ in $pattern are
     *                              escaped and the ESCAPE clause is emitted
     */
    public function __construct(
        private string $column,
        private string $pattern,
        private bool $escapeWildcards = false,
    ) {
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        return QueryPredicate::like($sqlIdentifier, $this->column, $this->pattern, $this->escapeWildcards);
    }
}
