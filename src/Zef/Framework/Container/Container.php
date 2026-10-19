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
 */
final readonly class Container implements ContainerInterface
{
    private ServiceStore $serviceStore;

    private RequestInstances $requestInstances;

    private Interceptions $interceptions;

    public function __construct()
    {
        $this->serviceStore = new ServiceStore();
        $this->requestInstances = $this->serviceStore->requestInstances();
        $this->interceptions = $this->serviceStore->interceptions();
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
     */
    public function createRequestScope(): RequestScope
    {
        return new RequestScope($this->requestInstances, $this);
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->serviceStore->has($id);
    }

    #[Override]
    public function get(string $id): mixed
    {
        if ($this->serviceStore->has($id)) {
            return $this->serviceStore->resolve($id, $this);
        }

        throw new NotFoundException('Service "' . $id . '" is not defined.');
    }
}
