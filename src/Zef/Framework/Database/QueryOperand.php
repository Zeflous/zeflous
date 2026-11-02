<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The where verbs' operand fold: the judgement the widened first
 * parameter forces -- what form did the operand arrive as, one
 * {@see Condition} object (whose operands are its own, so spelling the
 * triple beside it is refused) or the column/operator/value triple
 * (whose operator is required) -- recorded on the where state with the
 * caller's connector.
 *
 * The fold exists because the ceiling demanded it: the facade's two
 * widened verbs carried the same branch cluster twice, and the
 * operator-variety metric answered. One unit owns the judgement now;
 * the verbs own only the connector they spell.
 *
 * @internal
 */
final class QueryOperand
{
    /**
     * Judges one where operand and records it: the condition-object
     * tree through {@see QueryWhere::apply()}, the column/operator/value
     * triple through {@see QueryWhere::condition()}.
     *
     * The $verb name rides the refusals so the message names the verb
     * the caller actually typed.
     */
    public static function record(
        QueryWhere $queryWhere,
        Condition|SqlExpression|string $column,
        ?string $operator,
        mixed $value,
        string $connector,
        string $verb,
    ): void {
        if ($column instanceof Condition) {
            if ($operator !== null || $value !== null) {
                throw new QueryException(
                    "{$verb}() takes one Condition object or the column/operator/value triple, not both.",
                );
            }

            $queryWhere->apply($column, $connector);

            return;
        }

        if ($operator === null) {
            throw new QueryException(
                "{$verb}() requires an operator for the column form "
                . "(equality is spelled {$verb}('col', '=', \$value)).",
            );
        }

        $queryWhere->condition($column, $operator, $value, $connector);
    }
}
