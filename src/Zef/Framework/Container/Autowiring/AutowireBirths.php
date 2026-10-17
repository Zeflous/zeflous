<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;

/**
 * The compile pass's birth bookkeeping -- the memo, the chain and the
 * reuse record of one run (see {@see AutowireCompilerPass}).
 *
 * The memo answers whether a class is already birthed so a second
 * parameter naming it reuses the first birth; the chain holds the
 * in-progress births so a chain that closes onto itself is refused with
 * the walked path (the same message shape the runtime cycle guard
 * carries); the reuse record collects the identifiers resolved without
 * a birth -- the report's `reused` column. One instance serves one
 * {@see AutowireCompilerPass::process()} run and resets with it.
 */
final class AutowireBirths
{
    /** @var array<string, InstanceDefinition> the classes this run birthed, by their own name */
    private array $births = [];

    /** @var list<string> the in-progress birth chain, in planning order */
    private array $chain = [];

    /** @var list<string> the identifiers resolved without a birth this run */
    private array $reused = [];

    public function reset(): void
    {
        $this->births = [];
        $this->chain = [];
        $this->reused = [];
    }

    public function has(string $class): bool
    {
        return isset($this->births[$class]);
    }

    public function recordReuse(string $id): void
    {
        $this->reused[] = $id;
    }

    /**
     * Guards the chain: a class the in-progress chain already carries
     * is the circular birth, refused with the walked path (the same
     * message shape the runtime cycle guard carries).
     */
    public function guardChain(string $class): void
    {
        if (\in_array($class, $this->chain, true)) {
            throw AutowireReferenceRefusals::circularChain(implode(' -> ', [...$this->chain, $class]));
        }
    }

    /**
     * Opens the class's frame on the chain (its plan starts).
     */
    public function open(string $class): void
    {
        $this->chain[] = $class;
    }

    /**
     * Closes the class's frame on the chain (its plan completed) and
     * memoises the definition under the class's own name.
     */
    public function close(string $class, InstanceDefinition $instanceDefinition): void
    {
        array_pop($this->chain);
        $this->births[$class] = $instanceDefinition;
    }

    /**
     * @return list<InstanceDefinition> the births, in birth order
     */
    public function definitions(): array
    {
        return array_values($this->births);
    }

    /**
     * @return list<string> the birthed classes, in birth order
     */
    public function generated(): array
    {
        return array_keys($this->births);
    }

    /**
     * @return list<string> the identifiers resolved without a birth
     */
    public function reused(): array
    {
        return $this->reused;
    }
}
