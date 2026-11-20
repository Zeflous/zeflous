<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ParameterValues;
use Zef\Framework\Container\ValueDefinition;

/**
 * The value lane's materialisation bookkeeping -- the memo, the arms
 * and the report of one run (see {@see AutowireValueServices} for the
 * lane's orchestration).
 *
 * The memo is the materialised map itself: identifier => definition,
 * the same shape the births bookkeeping carries ({@see AutowireBirths}
 * -- the value IS the record, so a second consumer of an address
 * reuses the first materialisation by asking
 * {@see self::ensured()}). {@see self::ensure()} runs the whole
 * first-use sequence -- the memo hit, the occupancy refusal, the
 * missing-address refusal, the materialisation itself -- against the
 * parameters and the guard the lane threads in, so the decision
 * sequence lives with the record it maintains. Nothing is
 * materialised off the record: the definitions answer the run's set,
 * the identifiers answer the report column
 * ({@see AutowireResult::$values}), both in materialisation order.
 * One instance serves one
 * {@see AutowireCompilerPass::process()} run and resets with it.
 */
final class AutowireValueMaterialisations
{
    /** @var array<string, ValueDefinition> the materialised value services, by identifier */
    private array $materialised = [];

    /**
     * The first-use sequence: the memo hit answers, a foreign spelling
     * of the synthetic namespace refuses, a missing address refuses,
     * the declared type's own compatibility refuses (issue #800's
     * finding 4 -- the environment layer hands raw strings and no layer
     * casts them, so a string the parameters knob hands an int-typed
     * #[Value] parameter surfaces HERE, with the parameter, the address
     * and the way out named, never as a TypeError at the injection
     * seam), and the first consumer materialises the value service.
     */
    public function ensure(
        string $ownerId,
        string $name,
        string $key,
        ParameterValues $parameterValues,
        SyntheticValueGuard $syntheticValueGuard,
        ?string $builtinType = null,
    ): string {
        $id = SyntheticValueGuard::id($key);

        if (isset($this->materialised[$id])) {
            return $id;
        }

        if ($syntheticValueGuard->occupied($id)) {
            throw AutowireValueRefusals::declaredCollision($ownerId, $key);
        }

        if ($parameterValues->missing($key)) {
            throw AutowireValueRefusals::missingKey($ownerId, $name, $key);
        }

        $value = $parameterValues->value($key);

        if ($this->typeMismatched($builtinType, $value)) {
            throw AutowireValueRefusals::typeMismatch($ownerId, $name, $key, $value, (string) $builtinType);
        }

        $this->materialised[$id] = new ValueDefinition($id, $value);

        return $id;
    }

    /**
     * @return list<ValueDefinition> the materialised value services, in materialisation order
     */
    public function definitions(): array
    {
        return array_values($this->materialised);
    }

    /**
     * @return list<string> the materialised synthetic identifiers, in materialisation order
     */
    public function ids(): array
    {
        return array_keys($this->materialised);
    }

    /** Resets the bookkeeping for a new run. */
    public function reset(): void
    {
        $this->materialised = [];
    }

    /**
     * Whether the declared builtin type and the value's own runtime type
     * disagree on the closed scalar set (int, float, bool, string): the
     * one mismatch the lane refuses -- the parameters lane coerces
     * nothing, ever, and the mismatch the injection would have surfaced
     * as a TypeError naming no value source is the plan's own defect.
     * Array, iterable, mixed and untyped declarations keep their
     * anything-goes verdict (the value lane has typed nothing for them
     * to disagree with), and a null value stays the nullable family's
     * own spelling.
     *
     * @param null|string $builtinType the parameter's declared builtin type name
     */
    private function typeMismatched(?string $builtinType, mixed $value): bool
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
