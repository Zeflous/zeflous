<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * Storage and resolution logic behind {@see Container}.
 *
 * Kept separate from the PSR-11 facade so each class stays small and cohesive:
 * this class owns the factory map and the memoised instances, while the
 * resolution discipline itself -- the guarded factory call a re-entering id
 * refuses ({@see CycleGuard}) -- is the collaborator the memoisation writes
 * through, and {@see Container} only exposes the PSR-11 surface.
 *
 * @phpstan-type Factory Closure(Container): mixed
 */
final class ServiceStore
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    /** @var array<string, Closure(Container): mixed> */
    private array $factories = [];

    public function __construct(
        private readonly CycleGuard $cycleGuard = new CycleGuard(),
    ) {
    }

    /**
     * @param Closure(Container): mixed $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->resolved[$id]);
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->resolved)
            || \array_key_exists($id, $this->factories);
    }

    public function resolve(string $id, Container $container): mixed
    {
        if (\array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        return $this->resolved[$id] = $this->cycleGuard->resolve($id, $this->factories[$id], $container);
    }
}
