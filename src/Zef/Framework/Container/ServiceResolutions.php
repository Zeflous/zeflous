<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

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
 *
 * The lazy lane is the fourth rider over the same seam ({@see
 * LazyDeferrals}): an identifier the boot marked lazy resolves to a
 * native proxy whose materialiser runs the *guarded call itself* at
 * first use -- so the cycle guard, the interception onion and the
 * listeners all fire at materialisation, exactly as they would have
 * at resolution, just later. The memo holds the proxy (a singleton
 * materialises once, through the one proxy it keeps); a transient
 * answers a fresh proxy per resolution, each materialising its own
 * instance. An unmarked identifier resolves exactly as it always
 * did: the empty registry's `null` answers the unchanged lane.
 */
final readonly class ServiceResolutions
{
    public function __construct(
        private SingletonMemo $singletonMemo,
        private FactoryRegistrations $factoryRegistrations,
        private RequestInstances $requestInstances,
        private CycleGuard $cycleGuard,
        private Interceptions $interceptions = new Interceptions(),
        private LazyDeferrals $lazyDeferrals = new LazyDeferrals(),
        private ResolutionFrames $resolutionFrames = new ResolutionFrames(),
    ) {
    }

    /**
     * The lazy registry the boot marked ({@see LazyIdentities}) --
     * the store's reach-through for the facade and the guarded mark
     * ({@see LazyMarking}).
     */
    public function lazyIdentities(): LazyIdentities
    {
        return $this->lazyDeferrals->identities();
    }

    /**
     * Forgets the identifier's mark -- the `set()` re-registration's
     * own clearing step, beside the memo's.
     */
    public function forgetLazy(string $id): void
    {
        $this->lazyDeferrals->forget($id);
    }

    public function resolve(string $id, Container $container): mixed
    {
        if ($this->singletonMemo->has($id)) {
            return $this->singletonMemo->instance($id);
        }

        return match ($this->factoryRegistrations->lifetime($id)) {
            ServiceLifetime::REQUEST => $this->requestInstances->instance(
                $this->resolutionFrames->assertedNotCaptured($id),
                $container,
            ),
            ServiceLifetime::TRANSIENT => $this->lazyDeferrals->answering($id, $container, $this->guarded(...)),
            default => $this->singletonLane($id, $container),
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

    /**
     * The singleton lane: the frame ledger's wider-lifetime frame
     * rides the guarded call itself ({@see framedGuarded()}) so it is
     * open at both moments that call can run -- the eager factory
     * call the lane takes here, and the lazy proxy's materialisation,
     * later, at an arbitrary first-use call-site far from this lane --
     * and the memo records exactly what the factory resolved while
     * the request lane's captive guard can name the captor at either
     * moment.
     */
    private function singletonLane(string $id, Container $container): mixed
    {
        return $this->singletonMemo->answer(
            $id,
            fn (): mixed => $this->lazyDeferrals->answering($id, $container, $this->framedGuarded()),
        );
    }

    /**
     * The singleton lane's guarded call with the wider-lifetime frame
     * opened around it: the spelling the eager lane runs in place, and
     * the spelling the lazy proxy's materialiser embeds for its later
     * first use -- one closure, so the two moments cannot drift apart
     * again. The materialiser once ran the bare guarded call, and a
     * request-scoped dependency resolved beneath it slipped past the
     * captive guard into the singleton's forever memo -- the
     * cross-request leak the frame ledger exists to refuse.
     *
     * @return Closure(string, Container): mixed the frame-wrapped guarded call
     */
    private function framedGuarded(): Closure
    {
        return fn (string $id, Container $container): mixed => $this->resolutionFrames->withinSingleton(
            $id,
            fn (): mixed => $this->guarded($id, $container),
        );
    }
}
