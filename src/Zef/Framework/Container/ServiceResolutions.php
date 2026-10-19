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
 * seam every lane rides -- and the seam the interception lane rides
 * too ({@see Interceptions}): an armed registry wraps the guarded call
 * with the onion and fires its listeners around it, inside every
 * lane's build step so a replacement lands before the memo records
 * it, while an empty registry leaves the call's mechanics
 * byte-identical (the fast path below).
 */
final readonly class ServiceResolutions
{
    public function __construct(
        private SingletonMemo $singletonMemo,
        private FactoryRegistrations $factoryRegistrations,
        private RequestInstances $requestInstances,
        private CycleGuard $cycleGuard,
        private Interceptions $interceptions = new Interceptions(),
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
     * core ({@see CycleGuard} keeps the re-entry refusal), and the
     * interception lane's seam: an armed registry wraps the call and
     * fires its listeners around it; an empty one leaves the mechanics
     * alone.
     */
    public function guarded(string $id, Container $container): mixed
    {
        if (!$this->interceptions->armed()) {
            return $this->cycleGuard->resolve($id, $this->factoryRegistrations->factory($id), $container);
        }

        return $this->interceptions->run(
            $id,
            fn (): mixed => $this->cycleGuard->resolve(
                $id,
                $this->factoryRegistrations->factory($id),
                $container,
            ),
        );
    }
}
