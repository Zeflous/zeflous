<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The re-registration's invalidation walk (#770's unit): the whole
 * forget choreography a `set()` re-registration owes, wired once by
 * {@see ServiceStore} with the collaborators it needs -- the store
 * keeps the registration surface, this unit keeps what a rebind
 * invalidates.
 *
 * The walk answers one question in order: who else answers through
 * the re-registered identifier? Every alias still riding it, the
 * registry's own forget says, transitively -- a rider's rider is
 * exactly as stale as the rider, because the alias grammar's
 * transparency claim makes the alias the target's identity by
 * another name ({@see AliasRides}). Then the re-registered id's own
 * ride mark drops -- the id answers its own factory now, so a later
 * rebind of the old target must not reach through it -- and the id's
 * own memo and lazy mark go the way every earlier re-registration
 * already sent them: the previous instance must never answer again
 * ({@see SingletonMemo}), the deferral mark's declaration with it.
 */
final readonly class RebindInvalidations
{
    public function __construct(
        private AliasRides $aliasRides,
        private SingletonMemo $singletonMemo,
        private ServiceResolutions $serviceResolutions,
    ) {
    }

    /**
     * Forgets everything a re-registration invalidates: the riding
     * aliases' memo slots, the id's own ride mark, memo and lazy
     * mark.
     */
    public function forget(string $id): void
    {
        foreach ($this->aliasRides->forgottenFor($id) as $aliasId) {
            $this->singletonMemo->forget($aliasId);
        }

        $this->aliasRides->drop($id);
        $this->singletonMemo->forget($id);
        $this->serviceResolutions->forgetLazy($id);
    }
}
