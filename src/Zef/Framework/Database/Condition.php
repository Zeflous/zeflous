<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The where family's condition-object contract: one composable piece
 * of a WHERE clause that renders itself onto the lane's fragment
 * vocabulary ({@see QueryFragment}) through the identifier grammar.
 *
 * This is the answer the lean verb surface promised for the exotic
 * forms the reference carried as sugar ({@see QueryBuilder}'s own
 * docblock): a nested group and a column-vs-column comparison do not
 * return as new facade verbs -- the twenty-method ceiling the
 * architecture gate enforces -- but as OBJECTS, composed before the
 * query and applied by the one verb that already exists
 * (`where(Condition)`/`orWhere(Condition)`, {@see QueryBuilder::where()})
 * or by the criteria walk ({@see RepositoryCriteria}).
 *
 * The Domain-Driven direction the ROADMAP names is the reason the
 * objects exist at all: a domain composes its conditions as values --
 * passed around, combined, negated -- without touching the fluent
 * chain or knowing the identifier grammar; the rendering happens at
 * the composition boundary, where the grammar has always lived.
 *
 * The leaves ({@see ConditionComparison}, {@see ConditionColumnComparison},
 * {@see ConditionIn}, {@see ConditionNull}, {@see ConditionBetween},
 * {@see ConditionLike}) carry their operands unjudged -- the grammar
 * (operators, scalars, list emptiness) fires where it always fired,
 * at the composition verb, through the {@see QueryPredicate} folds:
 * one spelling, never two. The composites ({@see ConditionGroup},
 * {@see ConditionNot}) own the structural judgements instead: a group
 * with no member is a state the construction itself refuses.
 */
interface Condition
{
    /**
     * Renders this condition onto one fragment: the SQL text and its
     * ordered bound values, the pair every where-family fold answers.
     *
     * A group renders parenthesised (`(a AND b)`), a negation always
     * wraps its operand (`NOT (a)`) so no dialect's precedence table
     * is ever consulted -- the spelling is deterministic by
     * construction.
     */
    public function fragment(SqlIdentifier $sqlIdentifier): QueryFragment;
}
