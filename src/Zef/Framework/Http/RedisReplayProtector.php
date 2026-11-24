<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The cross-host Redis replay protector: the seen-table that makes a
 * replay id single-use across a whole host fleet, not only the
 * process tree the APCu spelling shares ({@see
 * ApcuReplayProtector}) or the one worker the in-memory spelling
 * keeps ({@see BoundedReplayProtector}).
 *
 * The verdict grammar is the interface's own closed set, spelled the
 * way the sister spellings spell it: a null id needs no protection,
 * a malformed one is rejected, a won claim is accepted, and a
 * refused claim is a duplicate -- the Redis claim's count IS the
 * accounting, one step that no second reading disambiguates, so the
 * spelling never reaches the UNAVAILABLE verdict the APCu twin
 * answers for its ambiguous add: a Redis reply is either the count
 * (a definite verdict) or the fail-closed refusal the store's own
 * normalisation throws with the command named ({@see
 * RedisReplayStore}), and the boundary's catch-all converts either
 * way -- never an unaccounted admission, the family's fail-closed
 * line held on every spelling.
 *
 * The clock the port carries is deliberately not spent: the
 * in-memory and APCu spellings lay the request's clock into the
 * table (the cutoff arithmetic reads it back), but the Redis
 * spelling's accounting is the server's own -- the TTL the winner
 * installs carries the window, and the key's expiry turns the id
 * fresh again without any request clock entering the accounting.
 */
final readonly class RedisReplayProtector implements ReplayProtectorInterface
{
    /** The seen-table's key namespace: one prefix, the Redis family's
     * own colon convention, no collision with the rate-limit counters. */
    private const string KEY_PREFIX = 'zef:replay:';

    /**
     * @param RedisReplayStore $redisReplayStore the atomic claim over the phpredis
     *                                          command seam
     * @param int              $windowMs        the duplicate window's length, in
     *                                          milliseconds (>= 1) -- an id re-seen
     *                                          inside the window is a duplicate, one
     *                                          re-seen after it is fresh again
     *
     * @throws HttpException when the window bound is violated
     */
    public function __construct(
        private RedisReplayStore $redisReplayStore,
        private int $windowMs = 300_000,
    ) {
        if ($this->windowMs < 1) {
            throw new HttpException('The security.auth replay window must be 1 or above.');
        }
    }

    #[Override]
    public function check(?string $replayId, int $nowMs): ReplayDecision
    {
        if ($replayId === null) {
            return ReplayDecision::NOT_REQUIRED;
        }

        if (AuthRequest::carriesAMalformedReplayId($replayId)) {
            return ReplayDecision::REJECTED;
        }

        // The window rounded up to whole seconds -- Redis's EXPIRE
        // keeps its own clock in seconds: the duplicate window stays
        // at least as long as configured, never silently shortened.
        // The constructor's window bound (windowMs >= 1) already
        // proves the round-up answers at least one second for every
        // admitted window -- windowMs + 999 >= 1000 -- so no separate
        // floor can ever engage, the same arithmetic the APCu twin
        // spells over its own store's TTL.
        $ttl = intdiv($this->windowMs + 999, 1000);

        $key = self::KEY_PREFIX . $replayId;

        return $this->redisReplayStore->claim($key, $ttl)
            ? ReplayDecision::ACCEPT
            : ReplayDecision::DUPLICATE;
    }
}
