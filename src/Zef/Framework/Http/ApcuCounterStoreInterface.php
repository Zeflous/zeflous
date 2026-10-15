<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The two atomic primitives a shared APCu rate-limit store is built on:
 * the counter increment and the once-per-window reset-epoch claim.
 *
 * Both primitives must be atomic per key -- the contract the reference's
 * `SharedRateLimitStoreInterface` docblock already stated ("implementations
 * MUST be atomic for read-modify-write on one key") while its APCu
 * implementation compromised it with a `apcu_fetch`/`apcu_store`
 * read-modify-write that races between workers (two workers can read the
 * same count and both write the same increment, losing one). This port
 * honours the contract instead of the bug: the counter is a single
 * `apcu_inc` step (atomic in APCu), and the reset epoch is claimed with
 * `apcu_add` (set-only-if-absent, atomic), so the aggregate accounting
 * across one process tree is exact.
 *
 * The interface exists so the fixed-window arithmetic above it ({@see
 * ApcuRateLimiter}) is pure and store-agnostic -- testable against an
 * in-memory double -- while the extension boundary ({@see
 * ApcuCounterStore}) stays thin enough to verify through namespace-shadowing
 * function doubles.
 */
interface ApcuCounterStoreInterface
{
    /**
     * Atomically increments the counter at the key, initialising it to one
     * with the given TTL when it does not exist yet.
     *
     * @param string $key the counter's storage key
     * @param int    $ttl the seconds a freshly initialised counter lives
     *
     * @return int the post-increment count
     */
    public function increment(string $key, int $ttl): int;

    /**
     * Claims the window's reset epoch: writes the epoch with the TTL when
     * the key does not exist (the window's first request, which fixes the
     * epoch every later request of the window reads), and answers the
     * existing epoch otherwise.
     *
     * @param string $key   the reset-epoch's storage key
     * @param int    $epoch the window's reset epoch, Unix seconds
     * @param int    $ttl   the seconds a freshly written epoch lives
     *
     * @return int the epoch the window resets at
     */
    public function claimResetEpoch(string $key, int $epoch, int $ttl): int;
}
