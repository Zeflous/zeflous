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
     * and the first consumer materialises the value service.
     */
    public function ensure(
        string $ownerId,
        string $name,
        string $key,
        ParameterValues $parameterValues,
        SyntheticValueGuard $syntheticValueGuard,
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

        $this->materialised[$id] = new ValueDefinition($id, $parameterValues->value($key));

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
}
