<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use Override;

/**
 * The negation composite: `NOT (inner)`, the operand ALWAYS wrapped
 * so no dialect's precedence table is ever consulted -- `NOT a AND b`
 * and `NOT (a AND b)` are different predicates, and the difference
 * is a bug class the wrapping removes by construction.
 *
 * The leaf-local NOT polarity (`NOT IN`, `IS NOT NULL`) stays on the
 * leaves' own negated flags -- those spellings are SQL-native and
 * carry no precedence question. This composite is the general
 * negation, for the operands that have none.
 */
final readonly class ConditionNot implements Condition
{
    public function __construct(
        private Condition $condition,
    ) {
    }

    #[Override]
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment
    {
        $inner = $this->condition->fragment($sqlIdentifier);

        return new QueryFragment('NOT (' . $inner->fragment . ')', $inner->params);
    }
}
