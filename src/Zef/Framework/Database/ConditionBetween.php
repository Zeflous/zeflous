<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The BETWEEN leaf: one `column BETWEEN ? AND ?` predicate as a value
 * object, the low bound bound before the high -- the order the fold
 * has always answered.
 */
final readonly class ConditionBetween implements Condition
{
    public function __construct(
        private string $column,
        private mixed $low,
        private mixed $high,
    ) {
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        return QueryPredicate::between($sqlIdentifier, $this->column, $this->low, $this->high);
    }
}
