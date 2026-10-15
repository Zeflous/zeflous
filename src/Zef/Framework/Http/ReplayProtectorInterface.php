<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The replay protection contract: how the boundary asks whether a
 * replay id is fresh -- ported from the reference's
 * `ReplayProtectorInterface` (legacy `zef-main`
 * `Framework/Security/Distributed/ReplayProtectorInterface.php`).
 *
 * The protector sees only the id and the clock: the window, the
 * capacity and the storage are the implementation's own ({@see
 * BoundedReplayProtector} is the in-memory spelling -- per-process
 * exactly as the reference shipped it, so a deployment that needs the
 * boundary across a process tree or a host fleet composes its own
 * protector over APCu or Redis the same way the rate-limit family's
 * stores do, and the interface is the seam that keeps that a
 * composition rather than a fork). A null id is the no-protection
 * answer the contract itself owns: {@see ReplayDecision::NOT_REQUIRED}.
 */
interface ReplayProtectorInterface
{
    /**
     * Checks one replay id at one clock.
     *
     * @param null|string $replayId the request's replay id, null when
     *                              none was carried
     * @param int         $nowMs    the request's clock, Unix
     *                              milliseconds
     */
    public function check(?string $replayId, int $nowMs): ReplayDecision;
}
