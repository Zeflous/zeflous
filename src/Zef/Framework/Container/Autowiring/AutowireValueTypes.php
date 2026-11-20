<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The value lane's own type judgment (issue #800's finding 4): whether the
 * declared builtin type and the value's own runtime type disagree on the
 * closed scalar set (int, float, bool, string) -- the one mismatch
 * {@see AutowireValueMaterialisations::ensure()} refuses.
 *
 * The judgment is the lane's own unit because the parameters lane coerces
 * nothing, ever: the environment layer hands raw strings and no layer
 * casts them, so the missing layer between the raw string and the typed
 * `#[Value]` parameter is a judgement, not a conversion -- and a judgement
 * the plan carries (the declared builtin type) against the value the
 * materialisation seam reads. Array, iterable, mixed and untyped
 * declarations keep their anything-goes verdict (the value lane has typed
 * nothing for them to disagree with), a non-scalar value is never judged
 * against a scalar declaration (the value lane spells no coercion, so it
 * spells no cross-family verdict either -- the judgement's own
 * {@see self::mismatched()} guard), and a null value stays the nullable
 * family's own spelling.
 */
final readonly class AutowireValueTypes
{
    /**
     * Whether the declared builtin type and the value's own runtime type
     * disagree on the closed scalar set: the one mismatch the lane refuses.
     *
     * @param null|string $builtinType the parameter's declared builtin type name
     */
    public static function mismatched(?string $builtinType, mixed $value): bool
    {
        if (!\is_scalar($value)) {
            return false;
        }

        $expected = match ($builtinType) {
            'int' => 'integer',
            'float' => 'double',
            'bool' => 'boolean',
            'string' => 'string',
            default => null,
        };

        return $expected !== null && \gettype($value) !== $expected;
    }
}
