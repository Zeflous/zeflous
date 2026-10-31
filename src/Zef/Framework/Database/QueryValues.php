<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The value and operator vocabulary every query condition and write
 * payload shares: the closed operator list, the scalar answer set a
 * bound value may carry (null/bool/int/float/string, or an explicit
 * {@see SqlExpression} escape hatch), and the two guards that enforce
 * them with the offending spelling named.
 *
 * @internal
 */
final class QueryValues
{
    public const array COMPARISON_OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>='];

    /**
     * Validates a comparison operator against the closed list.
     */
    public static function assertComparisonOperator(string $operator): string
    {
        if (!\in_array($operator, self::COMPARISON_OPERATORS, true)) {
            throw new QueryException(
                "Unknown comparison operator '{$operator}' (allowed: "
                . implode(' ', self::COMPARISON_OPERATORS) . ').',
            );
        }

        return $operator;
    }

    /**
     * Validates one bound value against the scalar answer set.
     */
    public static function assertScalar(mixed $value, string $context): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_float($value) || \is_string($value)) {
            return;
        }

        if ($value instanceof SqlExpression) {
            return;
        }

        throw new QueryException(
            "{$context} must be null, bool, int, float, string or SqlExpression (got " . get_debug_type($value) . ').',
        );
    }
}
