<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The column-vs-column leaf: one comparison between two columns of
 * the same row, the exotic form the reference carried as the
 * `whereColumn` sugar verb. No bound values -- both operands are
 * identifiers the grammar quotes, never parameters.
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
        return QueryPredicate::columnComparison($sqlIdentifier, $this->first, $this->operator, $this->second);
    }
}
