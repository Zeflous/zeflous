<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The two atomic primitives a shared APCu replay seen-table is built
 * on: the first-sight claim and the sight read-back.
 *
 * Both primitives must be atomic per key -- the same contract the
 * rate-limit family's {@see ApcuCounterStoreInterface} states, argued
 * the same way: a fetch-then-store read-modify-write races between
 * workers (two workers can both read "absent" and both admit the same
 * replayed id). The claim is a single `apcu_add` step (set-only-if-
 * absent, atomic), so exactly one worker of the tree ever wins a
 * replay id's first sight, and the read-back distinguishes the two
 * ways an add can refuse: the entry exists (a genuine duplicate
 * inside the window) or nothing readable exists (an accounting
 * outage the protector answers fail-closed).
 *
 * The interface exists so the verdict arithmetic above it ({@see
 * ApcuReplayProtector}) is pure and store-agnostic -- testable
 * against the namespace-shadowing function doubles the suite already
 * loads for the counter boundary -- while the extension boundary
 * ({@see ApcuReplayStore}) stays thin enough to verify completely.
 */
interface ApcuReplayStoreInterface
{
    /**
     * Claims the id's first sight: writes the sight's clock with the TTL
     * when the key does not exist, and answers false when it does.
     *
     * @param string $key      the sight's storage key
     * @param int    $seenAtMs the sight's clock, Unix milliseconds
     * @param int    $ttl      the seconds the sight lives -- the duplicate
     *                         window's own length, so an id whose sight
     *                         expired is fresh again exactly as the
     *                         in-memory table's cutoff makes it
     *
     * @return bool true when this call won the first sight, false when the
     *              key already exists
     */
    public function claimSight(string $key, int $seenAtMs, int $ttl): bool;

    /**
     * Reads the sight's clock back: the entry a refused claim must name to
     * be a genuine duplicate, and the absence that names an outage.
     *
     * @param string $key the sight's storage key
     *
     * @return null|int the sight's clock, or null when no readable entry exists
     */
    public function readSight(string $key): ?int;
}
