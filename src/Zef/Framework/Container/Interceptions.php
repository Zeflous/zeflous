<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The interception lane's registry (ROADMAP, "CONTAINER" area 9 -- the
 * reference container's `ServiceMiddlewarePipeline` +
 * `ContainerEventListenerRegistry` seam, adapted to the declarative
 * grammar): the store's own unit the resolution lane consults on
 * every construction, holding the one armed set the boot produced.
 *
 * The registry arms once per boot ({@see arm()}): the set materialises
 * -- its entries' arguments resolving through the plain lane, because
 * the onion it arms must not wrap its own links' construction -- and
 * every list is proven before the set itself is assigned, so a failed
 * materialisation leaves the honest not-armed state on a boot that is
 * already dying. A second arming is the refusal the sealed-once
 * discipline owes its name to: there is no public add path at all, the
 * declarative knobs being the only spelling an interceptor or listener
 * arrives by, so what the boot armed is what the process serves -- the
 * reference's freeze-time seal, by construction.
 *
 * A throwing interceptor or listener propagates raw: the resolution
 * lane has never wrapped a factory's own failure, and the host's
 * machinery keeps its own stack.
 */
final class Interceptions
{
    private ?InterceptionSet $interceptionSet = null;

    private bool $sealed = false;

    /**
     * Arms the registry: one boot's interception materialised through
     * the plain lane and sealed, once. A failed materialisation leaves
     * the honest not-armed state on a boot that is already dying --
     * the set is assigned only after its halves answered.
     */
    public function arm(InterceptorsMap $interceptorsMap, ListenersMap $listenersMap, Container $container): void
    {
        if ($this->sealed) {
            throw InterceptionRefusals::alreadyArmed();
        }

        $interceptionSet = new InterceptionSet(
            InterceptionPipeline::over($interceptorsMap, $container),
            InterceptionFires::over($listenersMap, $container),
        );
        $this->interceptionSet = $interceptionSet;

        $this->sealed = true;
    }

    /**
     * Whether the registry carries any interception -- the resolution
     * lane's fast path: an empty arming leaves the guarded call's
     * mechanics byte-identical, armed or not. The once-per-boot seal is
     * {@see arm()}'s own concern; a registry armed with nothing is
     * unarmed in every observable way but the second arming's refusal.
     */
    public function armed(): bool
    {
        $set = $this->interceptionSet;
        if ($set instanceof InterceptionSet) {
            return $set->carries();
        }

        return false;
    }

    /**
     * Runs one construction under the armed set -- the terminal itself
     * when the registry carries nothing (the caller's armed() check is
     * the fast path; this is the honest safety beneath it).
     *
     * @param Closure(): mixed $terminal the guarded factory call
     */
    public function run(string $id, Closure $terminal): mixed
    {
        $set = $this->interceptionSet;
        if ($set instanceof InterceptionSet) {
            return $set->run($id, $terminal);
        }

        return $terminal();
    }
}
