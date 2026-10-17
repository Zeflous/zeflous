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
 * @phpstan-type Factory Closure(Container): mixed
 */
final readonly class ServiceStore
{
    private SingletonMemo $singletonMemo;

    private FactoryRegistrations $factoryRegistrations;

    private ServiceResolutions $serviceResolutions;

    private RequestInstances $requestInstances;

    public function __construct(
        CycleGuard $cycleGuard = new CycleGuard(),
    ) {
        $this->singletonMemo = new SingletonMemo();
        $this->factoryRegistrations = new FactoryRegistrations();
        $this->requestInstances = new RequestInstances($this);
        $this->serviceResolutions = new ServiceResolutions(
            $this->singletonMemo,
            $this->factoryRegistrations,
            $this->requestInstances,
            $cycleGuard,
        );
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
