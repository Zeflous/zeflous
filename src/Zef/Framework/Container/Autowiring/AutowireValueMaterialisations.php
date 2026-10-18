<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\ValueDefinition;

/**
 * The value lane's materialisation bookkeeping -- the memo, the
 * definitions and the report of one run (see
 * {@see AutowireValueServices} for the lane itself).
 *
 * The memo answers whether an identifier is already materialised so a
 * second consumer of the same address reuses the first materialisation
 * ({@see self::ensured()}); the definitions list collects the value
 * services in materialisation order for the run's answer set; the
 * identifiers answer the report column
 * ({@see AutowireResult::$values}) -- nothing is materialised off the
 * record. One instance serves one
 * {@see AutowireCompilerPass::process()} run and resets with it.
 */
final class AutowireValueMaterialisations
{
    /** @var array<string, true> the identifiers this run materialised */
    private array $ids = [];

    /** @var list<ValueDefinition> the materialised value services, in materialisation order */
    private array $definitions = [];

    /** Whether the identifier is already materialised this run. */
    public function ensured(string $id): bool
    {
        return isset($this->ids[$id]);
    }

    /** Records one materialised value service. */
    public function add(ValueDefinition $valueDefinition): void
    {
        $this->ids[$valueDefinition->id] = true;
        $this->definitions[] = $valueDefinition;
    }

    /**
     * @return list<ValueDefinition> the materialised value services, in materialisation order
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<string> the materialised synthetic identifiers, in materialisation order
     */
    public function ids(): array
    {
        return array_keys($this->ids);
    }

    /** Resets the bookkeeping for a new run. */
    public function reset(): void
    {
        $this->ids = [];
        $this->definitions = [];
    }
}
