<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The injectable-value predicate the parameters family shares: whether
 * a value can travel as a parameter (and thereby as a value service) --
 * a scalar, null, or an array whose every element is itself injectable,
 * recursively.
 *
 * The answer names the offender rather than a bare verdict: the first
 * value under the tree that cannot travel (an object, a resource) is
 * handed back for the refusal message to quote, so the speller learns
 * which type offended at which address -- and a clean tree answers
 * null, which is itself an injectable value and never an offender. One
 * predicate, two spellings of refusal: the literal walk names the
 * address, the provider lane names the provider and its address -- the
 * decision is shared because the surface is, and the messages stay the
 * callers' because the speller differs.
 */
final readonly class ParameterInjectability
{
    /**
     * The first value under the tree that cannot travel as a parameter,
     * or null when the whole value is injectable.
     */
    public static function offender(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return \is_scalar($value) || $value === null ? null : $value;
        }

        foreach (array_keys($value) as $key) {
            $offender = self::offender($value[$key]);

            if ($offender !== null) {
                return $offender;
            }
        }

        return null;
    }
}
