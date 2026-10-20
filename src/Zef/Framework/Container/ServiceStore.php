<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * Storage and registration logic behind {@see Container}.
 *
 * Kept separate from the PSR-11 facade so each class stays small and cohesive:
 * this class owns the registration surface and the collaborators' wiring --
 * the registration core ({@see FactoryRegistrations}: the factory map and
 * the per-definition lifetime), the singleton memo ({@see SingletonMemo}),
 * the resolution lanes ({@see ServiceResolutions}) and the request-scope
 * machinery ({@see RequestInstances}, owned by the container itself so the
 * facade opens its own scopes) -- while {@see Container} only exposes the
 * PSR-11 surface and delegates here.
 *
 * The lifetime is the sharing policy (see {@see ServiceLifetime}): a
 * singleton memoises exactly as this store always has; a transient never
 * memoises anywhere; a request-scoped id resolves through the scope
 * machinery, which refuses honestly when no scope is open.
 *
 * The store also owns the interception registry's wiring
 * ({@see Interceptions}): constructed here, threaded into the
 * resolution lanes' guarded seam, and reached by the boot's arming
 * through the container's own accessor -- the machinery the boot
 * arms before it warms a single singleton. The namespace fallback
 * registry's wiring is the same posture ({@see NamespaceFallbacks}):
 * constructed here with the guard and the interception registry the
 * fallback construction rides, and reached by the container's miss
 * path through its own accessor -- the factories a host registers
 * for identifiers nobody declared.
 *
 * @phpstan-type Factory Closure(Container): mixed
 */
final readonly class ServiceStore
{
    private SingletonMemo $singletonMemo;

    private FactoryRegistrations $factoryRegistrations;

    private ServiceResolutions $serviceResolutions;

    private RequestInstances $requestInstances;

    private Interceptions $interceptions;

    private NamespaceFallbacks $namespaceFallbacks;

    public function __construct(
        CycleGuard $cycleGuard = new CycleGuard(),
    ) {
        $this->singletonMemo = new SingletonMemo();
        $this->factoryRegistrations = new FactoryRegistrations();
        $this->requestInstances = new RequestInstances($this);
        $this->interceptions = new Interceptions();
        $this->namespaceFallbacks = new NamespaceFallbacks($cycleGuard, $this->interceptions);
        $this->serviceResolutions = new ServiceResolutions(
            $this->singletonMemo,
            $this->factoryRegistrations,
            $this->requestInstances,
            $cycleGuard,
            $this->interceptions,
        );
    }

    /**
     * The interception registry the resolution lanes consult and the
     * boot arms ({@see Container::interceptions()} for the facade's
     * own reach-through).
     */
    public function interceptions(): Interceptions
    {
        return $this->interceptions;
    }

    /**
     * The namespace fallback registry the container's miss path
     * consults ({@see Container::namespaceFallbacks()} for the
     * facade's own reach-through).
     */
    public function namespaceFallbacks(): NamespaceFallbacks
    {
        return $this->namespaceFallbacks;
    }

    /**
     * The scope machinery the container's own facade opens scopes
     * through ({@see Container::createRequestScope()}).
     */
    public function requestInstances(): RequestInstances
    {
        return $this->requestInstances;
    }

    /**
     * @param Closure(Container): mixed $factory
     */
    public function set(
        string $id,
        Closure $factory,
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
    ): void {
        $this->factoryRegistrations->set($id, $factory, $serviceLifetime);
        $this->singletonMemo->forget($id);
    }

    public function has(string $id): bool
    {
        return $this->singletonMemo->has($id)
            || $this->factoryRegistrations->has($id);
    }

    public function resolve(string $id, Container $container): mixed
    {
        return $this->serviceResolutions->resolve($id, $container);
    }

    /**
     * The guarded factory call ({@see ServiceResolutions::guarded()} --
     * the request lane's seam into the registration core).
     */
    public function guarded(string $id, Container $container): mixed
    {
        return $this->serviceResolutions->guarded($id, $container);
    }
}
