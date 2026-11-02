<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The IN-list leaf: one `column [NOT] IN (...)` predicate as a value
 * object, over the same operand vocabulary the fluent verb accepts --
 * a literal list of scalars or a subquery builder whose SQL and
 * bindings merge after ours.
 */
final readonly class ConditionIn implements Condition
{
    /**
     * @param list<mixed>|QueryBuilder $values
     * @param bool                     $negated the NOT polarity the predicate fold carries
     */
    public function __construct(
        private string $column,
        private array|QueryBuilder $values,
        private bool $negated = false,
    ) {
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        return QueryPredicate::inList($sqlIdentifier, $this->column, $this->values, $this->negated);
    }
}
