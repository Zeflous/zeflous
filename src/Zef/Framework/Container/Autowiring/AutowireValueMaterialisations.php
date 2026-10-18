<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ValueDefinition;

/**
 * The value lane's materialisation bookkeeping -- the memo, the
 * definitions and the report of one run (see
 * {@see AutowireValueServices} for the lane itself).
 *
 * The memo is the materialised map itself: identifier => definition,
 * the same shape the births bookkeeping carries ({@see AutowireBirths}
 * -- the value IS the record, so a second consumer of an address
 * reuses the first materialisation by asking whether the identifier is
 * already materialised, and nothing is materialised off the record:
 * the definitions answer the run's set, the identifiers answer the
 * report column ({@see AutowireResult::$values}), both in
 * materialisation order). One instance serves one
 * {@see AutowireCompilerPass::process()} run and resets with it.
 */
final class AutowireValueMaterialisations
{
    /** @var array<string, ValueDefinition> the materialised value services, by identifier */
    private array $materialised = [];

    /** Whether the identifier is already materialised this run. */
    public function ensured(string $id): bool
    {
        return isset($this->materialised[$id]);
    }

    /** Records one materialised value service. */
    public function add(ValueDefinition $valueDefinition): void
    {
        $this->materialised[$valueDefinition->id] = $valueDefinition;
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
