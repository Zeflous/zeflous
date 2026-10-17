<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The registration core behind {@see ServiceStore}: the factory map and
 * the per-definition lifetime, written together by one `set` and read
 * back by identifier ({@see ServiceStore} keeps the memo and the
 * resolution lanes).
 *
 * The two maps are written as one: a registration is a factory AND its
 * sharing policy, never one without the other, so no read can observe
 * a lifetime whose factory is missing.
 *
 * @phpstan-type Factory Closure(Container): mixed
 */
final class FactoryRegistrations
{
    /** @var array<string, Closure(Container): mixed> */
    private array $factories = [];

    /** @var array<string, ServiceLifetime> */
    private array $lifetimes = [];

    /**
     * @param Closure(Container): mixed $factory
     */
    public function set(string $id, Closure $factory, ServiceLifetime $serviceLifetime): void
    {
        $this->factories[$id] = $factory;
        $this->lifetimes[$id] = $serviceLifetime;
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->factories);
    }

    /**
     * @return Closure(Container): mixed
     */
    public function factory(string $id): Closure
    {
        return $this->factories[$id];
    }

    /**
     * The registration's sharing policy; a registration written before
     * lifetimes were part of the set answers the singleton every
     * earlier declaration already had.
     */
    public function lifetime(string $id): ServiceLifetime
    {
        return $this->lifetimes[$id] ?? ServiceLifetime::SINGLETON;
    }
}
