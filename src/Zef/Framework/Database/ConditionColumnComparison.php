<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The column-vs-column leaf: one comparison between two columns of
 * the same row, the exotic form the reference carried as the
 * `whereColumn` sugar verb. No bound values -- both operands are
 * identifiers the quoting grammar answers, and the operator rides
 * the same closed list every comparison shares, so the leaf owns its
 * own trivial assembly the way the trivial composites ({@see ConditionNot},
 * {@see ConditionGroup}) own theirs.
 */
final readonly class ConditionColumnComparison implements Condition
{
    public function __construct(
        private string $first,
        private string $operator,
        private string $second,
    ) {
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        $op = QueryValues::assertComparisonOperator($this->operator);

        return new QueryFragment(
            $sqlIdentifier->columnPath($this->first) . ' ' . $op . ' ' . $sqlIdentifier->columnPath($this->second),
        );
    }
}
