<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The strict integer reading one knob's value passes through: the integer
 * seam every config-driven family's numeric knobs read via.
 *
 * A native int passes through untouched (a file or the default layer
 * declared it); a string -- the raw spelling an environment overlay
 * writes, `EnvSource` passing values through as the environment carries
 * them -- folds to its integer when it is all digits, and everything
 * else is rejected with the knob named: a float, a bool, a negative
 * sign or a spaced number halts the boot rather than silently reading
 * as zero, because a silently-mistyped budget is a limit the operator
 * believes is set while it is not. The range is the policy's own
 * invariant -- this reader owns the spelling.
 *
 * This is the integer sibling of the {@see StrictToggles} reading (the
 * legacy `zef-framework` `readBoolStrict` lineage, extracted for the
 * rate-limit family's numeric knobs).
 */
final class StrictIntegers
{
    /**
     * Reads one numeric knob's value: native int passthrough, all-digit
     * string fold, rejection with the knob named otherwise.
     *
     * @param string $knob the knob's family-qualified name, as the
     *                     rejection spells it (`security.rate_limit limit`)
     */
    public static function read(mixed $value, string $knob): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new HttpException(
            "The {$knob} knob must be an integer or an all-digit string, "
            . get_debug_type($value) . ' given.',
        );
    }
}
