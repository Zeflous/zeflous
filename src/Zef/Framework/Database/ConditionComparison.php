<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The where verb's own leaf: one column/operator/value comparison as
 * a value object, rendering through the same {@see QueryPredicate::comparison()}
 * fold the fluent verb rides -- the grammar fires at the composition
 * boundary, exactly where `where()` has always fired it.
 */
final readonly class ConditionComparison implements Condition
{
    public function __construct(
        private SqlExpression|string $column,
        private string $operator,
        private mixed $value,
    ) {
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        return QueryPredicate::comparison($sqlIdentifier, $this->column, $this->operator, $this->value);
    }
}
