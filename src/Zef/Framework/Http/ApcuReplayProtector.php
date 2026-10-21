<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The shared APCu replay protector: the seen-table that makes a
 * replay id single-use across a whole process tree, not only the
 * worker the request landed on.
 *
 * The in-memory spelling ({@see BoundedReplayProtector}) is
 * per-process exactly as the reference shipped it, and under a
 * process tree (FPM workers, FrankenPHP) a replayed id lands on a
 * different worker with probability (N-1)/N and is re-accepted --
 * the configured protection is probabilistically absent. This
 * spelling moves the table into the shared memory the tree already
 * shares: the first sight is claimed with one atomic `apcu_add`
 * ({@see ApcuReplayStore}), so exactly one worker of the tree ever
 * wins an id, and the TTL the claim carries IS the duplicate window
 * (rounded up, so the configured window is never silently
 * shortened) -- an id whose sight expired is fresh again exactly as
 * the in-memory table's cutoff makes it.
 *
 * The verdicts are the interface's own closed set, answered with the
 * same fail-closed posture the family keeps: a null id needs no
 * protection, a malformed one is rejected, a won claim is accepted,
 * a refused claim that reads a real sight back is the duplicate, and
 * a refused claim that reads nothing is an accounting outage -- the
 * fail-closed UNAVAILABLE the boundary's catch-all converts into the
 * 503, never an unaccounted admission. The capacity knob is the
 * in-memory table's own bound and has no APCu spelling: the shared
 * table's bound is APCu's own memory budget, the operator's
 * `apc.shm_size` declaration.
 */
final class ApcuReplayProtector implements ReplayProtectorInterface
{
    /** The seen-table's key namespace: one prefix, no collision with the rate-limit counters. */
    private const string KEY_PREFIX = 'zef.replay.';

    /**
     * @param int                       $windowMs the duplicate window's length, in
     *                                            milliseconds (>= 1) -- an id re-seen
     *                                            inside the window is a duplicate, one
     *                                            re-seen after it is fresh again
     * @param ApcuReplayStoreInterface  $store    the atomic sight boundary; the real
     *                                            APCu calls by default, the suite's
     *                                            doubles in tests
     *
     * @throws HttpException when the window bound is violated
     */
    public function __construct(
        private readonly int $windowMs = 300_000,
        private readonly ApcuReplayStoreInterface $store = new ApcuReplayStore(),
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

        $key = self::KEY_PREFIX . $replayId;

        // The window rounded up to whole seconds -- APCu's own clock:
        // the duplicate window stays at least as long as configured,
        // never silently shortened.
        $ttl = max(1, intdiv($this->windowMs + 999, 1000));

        if ($this->store->claimSight($key, $nowMs, $ttl)) {
            return ReplayDecision::ACCEPT;
        }

        return $this->store->readSight($key) !== null
            ? ReplayDecision::DUPLICATE
            : ReplayDecision::UNAVAILABLE;
    }
}
