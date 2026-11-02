<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The NULL leaf: one `column IS [NOT] NULL` predicate as a value
 * object -- the leaf the criteria map already reaches for when a
 * cell is null, spelled here as a first-class condition.
 */
final readonly class ConditionNull implements Condition
{
    /**
     * @param bool $negated the NOT polarity the predicate fold carries
     */
    public function __construct(
        private string $column,
        private bool $negated = false,
    ) {
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        return QueryPredicate::nullPredicate($sqlIdentifier, $this->column, $this->negated);
    }
}
