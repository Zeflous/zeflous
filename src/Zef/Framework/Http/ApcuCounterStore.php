<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The APCu extension boundary: the two atomic primitives ({@see
 * ApcuCounterStoreInterface}) as real `apcu_*` calls.
 *
 * This is the family's only file that touches the optional extension, and
 * it stays thin enough to verify completely: the boundary calls the
 * functions unqualified, so the doubles the test suite defines in this
 * very namespace shadow the global calls in every environment,
 * extension present or not -- no process isolation needed, no ignore
 * entry. The double-verified branches: a counter increment with its
 * initialisation TTL, a successful epoch claim, an existing epoch read
 * back, and the corrupt-entry fallbacks.
 *
 * Fail-closed shape: `apcu_inc` answers `false` only in failure states the
 * TTL-initialisation path rules out (a corrupt non-integer entry), and the
 * corrupt cases answer the safe value -- a counter reset to one, an epoch
 * re-claimed -- never a fabricated success. The store is selected only
 * through the `security.rate_limit.store` knob, and an accounting call
 * the runner cannot even make (the extension absent) surfaces through
 * the middleware's catch-all as the fail-closed 503, never an
 * unaccounted admission: an operator who declares `apcu` has stated
 * they want shared accounting, and silently substituting the
 * per-process limiter would multiply their limit by the worker count
 * while the knob says otherwise.
 *
 * Attribution: legacy `zef-main` `Framework/Security/ApcuRateLimiter.php`
 * (the store boundary; the atomic primitives replace the reference's
 * racing fetch/store pair, per the interface contract above).
 */
final class ApcuCounterStore implements ApcuCounterStoreInterface
{
    #[Override]
    public function increment(string $key, int $ttl): int
    {
        $count = apcu_inc($key, 1, $success, $ttl);

        return \is_int($count) ? $count : 1;
    }

    #[Override]
    public function claimResetEpoch(string $key, int $epoch, int $ttl): int
    {
        if (apcu_add($key, $epoch, $ttl)) {
            return $epoch;
        }

        return $this->fetchedEpoch(apcu_fetch($key), $epoch);
    }

    /**
     * The epoch an existing claim carries: the int the claimant wrote,
     * or the freshly computed epoch when the stored entry is corrupt
     * (anything but an int) -- the safe value, never a fabricated one.
     */
    private function fetchedEpoch(mixed $fetched, int $epoch): int
    {
        return \is_int($fetched) ? $fetched : $epoch;
    }
}
