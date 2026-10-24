<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Minimal, reflection-free service locator implementing PSR-11.
 *
 * This is the seed of the ZEF container: it exposes the PSR-11 surface and
 * delegates storage and resolution to {@see ServiceStore}. Autowiring, compiler
 * passes, tags and per-request scopes described in `ROADMAP.md` are layered on
 * top of this class, not inside it -- the scope entry point below is the one
 * deliberate widening: a request scope is a resolution surface over the same
 * store, so the facade that owns the store owns its opening too. The
 * interception registry's accessor is the second: the boot's lanes arm the
 * registry the resolution lanes consult, and the facade that owns the store
 * owns the reach-through ({@see Interceptions} -- the registry refuses a
 * second arming itself, so what the accessor exposes cannot be re-armed).
 * The namespace fallback registry's accessor is the third: the miss path
 * consults the registry the composition root registered its fallback
 * factories against ({@see NamespaceFallbacks}), and the facade that owns
 * the store owns that reach-through too. The lazy registry's accessor is
 * the fourth: the boot's lanes mark the identifiers the resolution lanes
 * defer behind native proxies ({@see LazyIdentities}), and the facade that
 * owns the store owns that reach-through too.
 */
final readonly class Container implements ContainerInterface
{
    private ServiceStore $serviceStore;

    private RequestInstances $requestInstances;

    private Interceptions $interceptions;

    private NamespaceFallbacks $namespaceFallbacks;

    private LazyIdentities $lazyIdentities;

    private ContainerAnswers $containerAnswers;

    public function __construct()
    {
        $this->serviceStore = new ServiceStore();
        $this->requestInstances = $this->serviceStore->requestInstances();
        $this->interceptions = $this->serviceStore->interceptions();
        $this->namespaceFallbacks = $this->serviceStore->namespaceFallbacks();
        $this->lazyIdentities = $this->serviceStore->lazyIdentities();
        $this->containerAnswers = new ContainerAnswers($this->serviceStore, $this->namespaceFallbacks);
    }

    /**
     * The interception registry the resolution lanes consult and the
     * boot arms once, before a single singleton warms: the
     * declarative `interceptors` and `listeners` knobs' materialised
     * form (the construction-time extension points, ROADMAP area 9).
     */
    public function interceptions(): Interceptions
    {
        return $this->interceptions;
    }

    /**
     * The namespace fallback registry the miss path consults: the
     * longest-prefix factories a composition root registers for
     * identifiers nobody declared (ROADMAP area 9's namespace half
     * -- definitions on demand, spelled in code because the factory
     * receives the identifier it resolves).
     *
     * The registry reaches the host through this read: the
     * composition root's own wiring (outside this repository) is the
     * caller the psalm baseline's unused verdict records.
     */
    public function namespaceFallbacks(): NamespaceFallbacks
    {
        return $this->namespaceFallbacks;
    }

    /**
     * The lazy registry the resolution lanes consult and the boot's
     * lanes mark: the identifiers that resolve to a native lazy proxy
     * deferring their guarded construction to first use (ROADMAP area
     * 7's proxy half). The marks are the boot's own post-install step
     * ({@see ContainerRegistrations::lazyMarks()}), and a `set()`
     * re-registration forgets them with the memo -- the eager surface
     * re-registers eagerly.
     */
    public function lazyIdentities(): LazyIdentities
    {
        return $this->lazyIdentities;
    }

    /**
     * Marks a registered identifier lazy behind the class its native
     * proxy stands in for -- the programmatic spelling of the
     * declarative `lazy` key ({@see LazyKey}), the composition root's
     * own deferral request beside the grammar's. The identifier must
     * already carry a registration, and a request lifetime refuses
     * (its deferred materialisation could not name its scope); a
     * `set()` re-registration forgets the mark with the memo.
     *
     * @param class-string $class
     */
    public function markLazy(string $id, string $class): void
    {
        LazyMarking::mark($this->serviceStore->factoryRegistrations(), $this->lazyIdentities, $id, $class);
    }

    /**
     * Answers a subset locator over the named identifiers (ROADMAP
     * area 10's service locator): a light read-only PSR-11 view whose
     * resolution delegates to this container's own lanes -- the same
     * memoisation, the same lazy deferral, the same refusals -- with
     * the subset's boundary enforced, not advisory. Every asked
     * identifier must already be one the container can answer, and
     * the boot's `@`-prefixed machinery is refused with the ask.
     *
     * The composition root asks: the locator is the host-facing half
     * of the PSR-11 story (#487's lane), consumed from application
     * wiring that lives outside this repository -- the psalm
     * baseline's unused verdict is that boundary, not dead code.
     */
    public function locator(string ...$ids): ContainerLocator
    {
        return new ContainerLocator($this, ...$ids);
    }

    /**
     * Registers a factory under an identifier, with the definition's
     * sharing policy (a singleton by default -- the behaviour every
     * earlier registration already had).
     *
     * @param Closure(self): mixed $factory
     */
    public function set(
        string $id,
        Closure $factory,
        ServiceLifetime $serviceLifetime = ServiceLifetime::SINGLETON,
    ): void {
        $this->serviceStore->set($id, $factory, $serviceLifetime);
    }

    /**
     * Opens a request scope: the per-request resolution surface the
     * request-scoped declarations resolve in ({@see RequestScope},
     * wired to the store's scope machinery).
     *
     * The runtime lane this seam waits for is the HTTP roadmap's
     * §5.1 runtime adapter (phase 2, the persistent-worker lane): the
     * adapter opens one scope per worker request, resolves the
     * request's graph through it, and closes it at the request's end
     * -- the scope's own get/has/close surface consumed as one
     * lifecycle. Until that lane lands, the composition root and the
     * test battery are the callers, and the psalm baseline carries
     * the deferred consumer honestly.
     */
    public function createRequestScope(): RequestScope
    {
        return new RequestScope($this->requestInstances, $this);
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->containerAnswers->carries($id);
    }

    #[Override]
    public function get(string $id): mixed
    {
        return $this->containerAnswers->answer($id, $this);
    }
}
