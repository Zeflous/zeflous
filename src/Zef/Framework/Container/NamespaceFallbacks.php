<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The namespace fallback registry (ROADMAP, "CONTAINER" area 9's
 * namespace half -- the reference container's
 * `NamespaceFallbackResolver`, adapted to the resolution seam the
 * store already owns): the longest-prefix factories a host registers
 * for identifiers the container never declared, consulted at the
 * get() miss -- definitions on demand, PHP-DI's own posture.
 *
 * Registration is the composition root's programmatic act
 * ({@see Container::namespaceFallbacks()}), never a declarative knob:
 * a fallback factory receives the identifier it is resolving -- data
 * the declarative grammar cannot spell, its arguments being fixed at
 * declaration time -- so the host that wants a fallback lane spells
 * its factories in code, the same posture {@see Container::set()}
 * gives the host's own closures.
 *
 * The consultation seam is the miss path itself: `get()` on an
 * identifier the store does not carry resolves through the covering
 * fallback (the longest collapsed prefix that contains it), and
 * `has()` answers true for what a fallback covers -- the PSR-11 letter,
 * has() being exactly the promise that get() will not refuse the
 * identifier. An empty registry answers nothing: the miss path's
 * behaviour is byte-identical to the no-registry boot.
 *
 * The sharing policies ride the registry's own budget of 64 prefixes
 * (re-registering a prefix replaces its factory, never a second slot):
 * a singleton fallback memoises per identifier -- a fresh
 * {@see SingletonMemo}, the store's own unit, over its own ids -- a
 * transient fallback builds on every resolution, and the request scope
 * is the honest refusal (a fallback identifier is not a scope member;
 * no scope machinery will ever open for it).
 *
 * A fallback construction is construction: it rides the same guarded
 * seam the registered lanes ride -- the {@see CycleGuard} frame (a
 * fallback factory that re-enters its own identifier, directly or
 * through an intermediary, is the cycle the guard refuses with the
 * walked chain) and the interception set the boot armed (the onion
 * wraps the build, the listeners fire around it) -- so an intercepted
 * container intercepts its fallbacks too, exactly the reference's own
 * audit finding. A throwing fallback factory propagates raw, the same
 * discipline the registered factories keep; a null answer is a legal
 * instance (the value-null stance the whole container carries).
 */
final class NamespaceFallbacks
{
    /**
     * The registered fallbacks: collapsed prefix => its entry.
     *
     * @var array<string, array{factory: Closure(Container, string): mixed, lifetime: ServiceLifetime}>
     */
    private array $fallbacks = [];

    private SingletonMemo $singletonMemo;

    private readonly FallbackConstructions $fallbackConstructions;

    public function __construct(
        CycleGuard $cycleGuard,
        Interceptions $interceptions,
        private readonly ResolutionFrames $resolutionFrames = new ResolutionFrames(),
    ) {
        $this->singletonMemo = new SingletonMemo();
        $this->fallbackConstructions = new FallbackConstructions($cycleGuard, $interceptions);
    }

    /**
     * Registers the fallback factory for a namespace prefix -- the
     * longest covering prefix wins at the miss, and a re-registration
     * replaces the earlier factory (the composition root's own rebind)
     * while forgetting every memoised answer, because the memo cannot
     * prove which instance the replaced factory would have built.
     *
     * @param Closure(Container, string): mixed $factory receives the container and the missed identifier
     */
    public function fallback(
        string $prefix,
        Closure $factory,
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
    ): void {
        $collapsed = new NamespaceFallbackGrammar()->collapsed($prefix, $serviceLifetime, $this->fallbacks);

        $this->fallbacks[$collapsed] = ['factory' => $factory, 'lifetime' => $serviceLifetime];
        $this->forgetMemoised();
    }

    /**
     * Whether any registered fallback covers the identifier -- the
     * has() consultation. An empty registry answers nothing, the
     * fast path the miss path never pays past.
     */
    public function covers(string $id): bool
    {
        return $this->covering($id) !== null;
    }

    /**
     * Resolves the identifier through its covering fallback -- the
     * get() miss path's own resolution. An identifier no fallback
     * covers is the miss itself, refused exactly as the store refuses
     * it.
     */
    public function resolve(string $id, Container $container): mixed
    {
        $entry = $this->covering($id);

        if ($entry === null) {
            throw new NotFoundException('Service "' . $id . '" is not defined.');
        }

        if ($entry['lifetime'] === ServiceLifetime::TRANSIENT) {
            return $this->fallbackConstructions->run($id, $entry['factory'], $container);
        }

        return $this->singletonMemo->answer(
            $id,
            fn (): mixed => $this->resolutionFrames->withinSingleton(
                $id,
                fn (): mixed => $this->fallbackConstructions->run($id, $entry['factory'], $container),
            ),
        );
    }

    /**
     * The covering fallback: the longest collapsed prefix that contains
     * the identifier's collapsed spelling.
     *
     * @return null|array{factory: Closure(Container, string): mixed, lifetime: ServiceLifetime}
     */
    private function covering(string $id): ?array
    {
        $longest = new NamespaceCoverings()->longestPrefix($this->fallbacks, $id);

        if ($longest === null) {
            return null;
        }

        return $this->fallbacks[$longest];
    }

    /**
     * Forgets every memoised fallback answer -- a replaced factory's
     * own invalidation (a fresh memo, because the per-identifier keys
     * the old one carries cannot be attributed back to the prefix that
     * built them).
     */
    private function forgetMemoised(): void
    {
        $this->singletonMemo = new SingletonMemo();
    }
}
