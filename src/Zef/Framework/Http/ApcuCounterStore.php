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
 * back, and the refusal arms -- a corrupt answer, a disabled extension,
 * a claim nothing reads back.
 *
 * Fail-closed shape: an `apcu_inc` that answers anything but a
 * successful integer count -- the extension disabled at runtime (a
 * CLI worker without `apc.enable_cli`), the shared memory exhausted, a
 * corrupt entry under the accounting key -- is an accounting outage,
 * and so is an epoch claim that cannot read a real claim back. Both
 * arms throw the layer's exception, which the rate-limit middleware's
 * catch-all converts into the fail-closed 503: an accounting call that
 * cannot even be made never becomes an unaccounted admission or a
 * fabricated reset, whatever refused it. The store is selected only
 * through the `security.rate_limit.store` knob, and an operator who
 * declares `apcu` has stated they want shared accounting -- silently
 * substituting the per-process limiter would multiply their limit by
 * the worker count while the knob says otherwise.
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

        if ($success !== true || !\is_int($count)) {
            throw new HttpException('The APCu accounting refused the increment.');
        }

        return $count;
    }

    #[Override]
    public function claimResetEpoch(string $key, int $epoch, int $ttl): int
    {
        if (apcu_add($key, $epoch, $ttl)) {
            return $epoch;
        }

        $fetched = apcu_fetch($key);

        if (!\is_int($fetched)) {
            throw new HttpException('The APCu accounting refused the reset epoch.');
        }

        return $fetched;
    }
}
