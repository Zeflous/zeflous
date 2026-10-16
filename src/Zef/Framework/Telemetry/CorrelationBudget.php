<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The attribute table's aggregate byte budget: the running total
 * the table's validation spends every entry against -- the
 * accumulator the {@see CorrelationAttributes} census reads, with
 * the scalar arithmetic it owns.
 *
 * The budget is a class rather than a local counter because the
 * arithmetic has a shape of its own: one scalar value's share is
 * the rendered width the wire would actually pay, not the storage
 * width the engine keeps -- a null renders four bytes, a bool four
 * or five, an integer up to twenty, a float up to twenty-four --
 * and that width policy is a unit the mutation run reaches as one
 * target, not a formula scattered through the validation's loop.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Observability/CorrelationContext.php`): the aggregate
 * ceiling and the per-scalar rendered widths move verbatim, in the
 * accumulator's own class.
 */
final class CorrelationBudget
{
    private int $bytes = 0;

    /**
     * @param int $ceiling the aggregate byte ceiling this budget
     *                     spends against ({@see CorrelationAttributes::
     *                     MAX_ATTRIBUTE_BYTES}, the table census's own)
     */
    public function __construct(
        private readonly int $ceiling,
    ) {
    }

    /**
     * Spends one entry's rendered width against the budget: the
     * key's bytes plus the value's.
     *
     * @param string                     $key   the entry's validated key
     * @param null|bool|float|int|string $value the entry's validated value
     */
    public function spend(string $key, bool|float|int|string|null $value): void
    {
        $this->bytes += mb_strlen($key, '8bit') + $this->scalarByteLength($value);
    }

    /**
     * Whether the budget has passed the table's aggregate ceiling.
     */
    public function exceeded(): bool
    {
        return $this->bytes > $this->ceiling;
    }

    /**
     * One scalar value's share of the aggregate budget: the rendered
     * width the wire would actually pay, not the storage width the
     * engine keeps -- a null renders four bytes, a bool four or five,
     * an integer up to twenty, a float up to twenty-four.
     */
    private function scalarByteLength(bool|float|int|string|null $value): int
    {
        if ($value === null) {
            return 4;
        }

        if (\is_bool($value)) {
            return $value ? 4 : 5;
        }

        if (\is_int($value)) {
            return 20;
        }

        if (\is_float($value)) {
            return 24;
        }

        return mb_strlen($value, '8bit');
    }
}
