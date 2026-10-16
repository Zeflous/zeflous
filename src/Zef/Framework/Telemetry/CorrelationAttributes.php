<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

use InvalidArgumentException;

/**
 * The correlation context's attribute table: the bounded map the
 * causal context carries -- the validation over the bounds census,
 * the table's entry grammar and its aggregate budget.
 *
 * The bounds are the wire's, not the application's: a correlation
 * context travels in propagation headers and log records, and a
 * table without a byte budget would let a caller smuggle an
 * unbounded payload onto every hop that echoes it. The census --
 * sixteen entries, a sixty-four-byte key, a two-hundred-fifty-six
 * byte value, four thousand ninety-six bytes aggregate -- mirrors
 * the {@see \Zef\Framework\Http\AuthAttributes} bounds the auth
 * family landed, with the one posture the two tables genuinely
 * differ on: the auth boundary REFUSES sensitive keys outright (no
 * credential material may travel as labelled cargo), the
 * correlation table REDACTS them (a causal diagnostic may point at
 * the material without carrying it -- the sha256 digest is the
 * pointer).
 *
 * The table's other face -- the redaction its diagnostics answer
 * -- is the {@see CorrelationRedaction} unit's own: the digest
 * census and the redacted view, deterministic by design (the same
 * sensitive value answers the same digest every time, so two log
 * lines that correlate the same credential also correlate each
 * other, and a grep for the digest finds every line the value
 * produced).
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CorrelationContext.php`): the bounds,
 * the key grammar, the scalar-only values and the aggregate budget
 * move verbatim; the port's deviations are the decomposition -- the
 * running byte budget lives in its own accumulator ({@see
 * CorrelationBudget}), the redaction in its own digest census
 * ({@see CorrelationRedaction}), this class answering the validation
 * alone -- and the dropped empty-key guard, which the port's own
 * mutation campaign proved unreachable-in-effect: the grammar
 * refuses the empty key with the same refusal, so the guard read a
 * line the grammar already refuses.
 */
final class CorrelationAttributes
{
    /** The table's entry ceiling. */
    public const int MAX_ATTRIBUTES = 16;

    /** One attribute key's ceiling. */
    public const int MAX_ATTRIBUTE_KEY_BYTES = 64;

    /** One attribute string value's ceiling. */
    public const int MAX_ATTRIBUTE_VALUE_BYTES = 256;

    /** The table's aggregate byte budget. */
    public const int MAX_ATTRIBUTE_BYTES = 4096;

    /**
     * Validates the attribute table against the bounds census and
     * answers the normalised map.
     *
     * @param array<mixed> $attributes the table as the caller offered it
     *
     * @return array<string, null|bool|float|int|string> the validated table
     *
     * @throws InvalidArgumentException when the table exceeds the count,
     *                                  a key fails its grammar, a value is
     *                                  not scalar or null, or the aggregate
     *                                  budget is exceeded
     */
    public static function validated(array $attributes): array
    {
        if (\count($attributes) > self::MAX_ATTRIBUTES) {
            throw new InvalidArgumentException('Correlation attributes exceed the maximum count.');
        }

        $normalized = [];
        $correlationBudget = new CorrelationBudget(self::MAX_ATTRIBUTE_BYTES);

        foreach ($attributes as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Invalid correlation attribute key.');
            }

            $value = self::entry($key, $value);
            $normalized[$key] = $value;

            $correlationBudget->spend($key, $value);

            if ($correlationBudget->exceeded()) {
                throw new InvalidArgumentException('Correlation attributes exceed the aggregate size limit.');
            }
        }

        return $normalized;
    }

    /**
     * One table entry's validation: the key's grammar and bound, the
     * value's shape and bound -- the value answered narrowed, ready
     * for the budget's arithmetic.
     *
     * @param string $key   the entry's key, already proven a string
     * @param mixed  $value the value as the caller offered it
     *
     * @return null|bool|float|int|string the validated value
     *
     * @throws InvalidArgumentException when the key fails its grammar or
     *                                  bound, or the value is neither
     *                                  scalar nor null
     */
    private static function entry(string $key, mixed $value): bool|float|int|string|null
    {
        if (mb_strlen($key, '8bit') > self::MAX_ATTRIBUTE_KEY_BYTES) {
            throw new InvalidArgumentException('Invalid correlation attribute key.');
        }

        if (preg_match('/^[A-Za-z0-9_.-]+$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid correlation attribute key.');
        }

        if ($value !== null && !\is_scalar($value)) {
            throw new InvalidArgumentException('Correlation attribute values must be scalar or null.');
        }

        if (\is_string($value) && mb_strlen($value, '8bit') > self::MAX_ATTRIBUTE_VALUE_BYTES) {
            throw new InvalidArgumentException(
                'Correlation attribute string value exceeds the maximum length.',
            );
        }

        return $value;
    }
}
