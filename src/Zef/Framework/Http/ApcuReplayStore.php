<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The APCu extension boundary of the replay family: the two atomic
 * sight primitives ({@see ApcuReplayStoreInterface}) as real
 * `apcu_*` calls.
 *
 * This stays the family's replay-side twin of the rate-limit counter
 * boundary ({@see ApcuCounterStore}): the calls are unqualified, so
 * the namespace-shadowing function doubles the suite already loads
 * for that boundary cover this one too, in every environment,
 * extension present or not. The boundary itself is thin enough to
 * verify completely: a first-sight claim, a refused claim's read-back,
 * and the refusal arms.
 *
 * Fail-closed shape: an `apcu_add` that answers false while nothing
 * readable exists under the key -- the extension disabled at runtime
 * (a CLI worker without `apc.enable_cli`), the shared memory
 * exhausted, a corrupt entry -- is an outage the protector answers
 * with the fail-closed {@see ReplayDecision::UNAVAILABLE} (the
 * boundary's catch-all converts it into the 503), never an
 * unaccounted admission or a fabricated duplicate. The store is
 * selected only through the `security.auth.replay_store` knob, and an
 * operator who declares `apcu` has stated they want the seen-table
 * shared across the process tree.
 */
final class ApcuReplayStore implements ApcuReplayStoreInterface
{
    #[Override]
    public function claimSight(string $key, int $seenAtMs, int $ttl): bool
    {
        return apcu_add($key, $seenAtMs, $ttl);
    }

    #[Override]
    public function readSight(string $key): ?int
    {
        /** @var mixed $sight the fetched entry is the store's own mixed answer */
        $sight = apcu_fetch($key);

        return \is_int($sight) ? $sight : null;
    }
}
