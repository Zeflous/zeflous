<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The resolution lanes over the registration core -- the store's own
 * dispatch ({@see ServiceStore} keeps the registration surface and the
 * collaborators' wiring).
 *
 * One lane per sharing policy ({@see ServiceLifetime}), dispatched in
 * one `match`: a singleton resolves through the memo's answer-or-build
 * seam ({@see SingletonMemo}); a transient pays the guarded factory
 * call every resolution and never memoises anywhere; a request-scoped
 * id resolves through the scope machinery ({@see RequestInstances}),
 * which refuses honestly when no scope is open. The guarded factory
 * call itself ({@see CycleGuard} keeps the re-entry refusal) is the
 * seam every lane rides.
 */
final readonly class ServiceResolutions
{
    public function __construct(
        private SingletonMemo $singletonMemo,
        private FactoryRegistrations $factoryRegistrations,
        private RequestInstances $requestInstances,
        private CycleGuard $cycleGuard,
    ) {
    }

    public function resolve(string $id, Container $container): mixed
    {
        if ($this->singletonMemo->has($id)) {
            return $this->singletonMemo->instance($id);
        }

        return match ($this->factoryRegistrations->lifetime($id)) {
            ServiceLifetime::REQUEST => $this->requestInstances->instance($id, $container),
            ServiceLifetime::TRANSIENT => $this->guarded($id, $container),
            default => $this->singletonMemo->answer($id, fn (): mixed => $this->guarded($id, $container)),
        };
    }

    /**
     * The guarded factory call -- the lanes' seam into the registration
     * core ({@see CycleGuard} keeps the re-entry refusal).
     */
    public function guarded(string $id, Container $container): mixed
    {
        return $this->cycleGuard->resolve($id, $this->factoryRegistrations->factory($id), $container);
    }
}
