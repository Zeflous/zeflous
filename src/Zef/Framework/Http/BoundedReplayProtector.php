<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The bounded in-memory replay protector: the seen-table that makes a
 * replay id single-use inside its window -- ported from the
 * reference's `BoundedInMemoryReplayProtector` (legacy `zef-main`
 * `Framework/Security/Distributed/BoundedInMemoryReplayProtector.php`),
 * move-verbatim in its verdicts and its bounds.
 *
 * The table is per-process exactly as the reference shipped it: the
 * window, the capacity and the accounting live in this object, so the
 * protection covers the worker the request landed on -- a deployment
 * that runs a process tree or a host fleet behind the boundary
 * composes a shared protector over APCu or Redis through {@see
 * ReplayProtectorInterface}, the same composition the rate-limit
 * family's stores model, and the interface is the seam that keeps that
 * a composition rather than a fork.
 *
 * The sweep is the reference's own every-check sweep, kept deliberately:
 * a capacity-triggered sweep would let an expired entry survive as a
 * phantom duplicate (the verdicts change, `DUPLICATE` where `ACCEPT`
 * belongs), and correctness of the refusal is the whole point of the
 * table. The cost is bounded by the capacity knob, and the sweep runs
 * only for requests that carry a replay id at all -- the table's
 * `NOT_REQUIRED` early return keeps the id-less hot path untouched.
 */
final class BoundedReplayProtector implements ReplayProtectorInterface
{
    /**
     * The seen-table: one entry per accepted replay id, its arrival
     * clock.
     *
     * @var array<string, int>
     */
    private array $seen = [];

    /**
     * @param int $capacity the seen-table's entry ceiling (>= 1) -- a
     *                      full table answers the fail-closed
     *                      UNAVAILABLE rather than admitting untracked
     * @param int $windowMs the duplicate window's length, in
     *                      milliseconds (>= 1) -- an id re-seen inside
     *                      the window is a duplicate, one re-seen after
     *                      it is fresh again
     *
     * @throws HttpException when a bound is violated
     */
    public function __construct(
        private readonly int $capacity = 1024,
        private readonly int $windowMs = 300_000,
    ) {
        if ($this->capacity < 1) {
            throw new HttpException('The security.auth replay capacity must be 1 or above.');
        }

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

        if ($replayId === '' || mb_strlen($replayId) > AuthRequest::MAX_REPLAY_ID_CHARS) {
            return ReplayDecision::REJECTED;
        }

        $cutoff = $nowMs - $this->windowMs;

        foreach ($this->seen as $id => $seenAt) {
            if ($seenAt >= $cutoff) {
                continue;
            }

            unset($this->seen[$id]);
        }

        if (isset($this->seen[$replayId])) {
            return ReplayDecision::DUPLICATE;
        }

        if (\count($this->seen) >= $this->capacity) {
            return ReplayDecision::UNAVAILABLE;
        }

        $this->seen[$replayId] = $nowMs;

        return ReplayDecision::ACCEPT;
    }
}
