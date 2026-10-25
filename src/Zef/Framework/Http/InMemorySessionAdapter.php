<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;
use Override;

/**
 * The session port's in-memory driver: the zero-dependency engine's
 * own storage, the same role the in-memory rate-limit store plays in
 * its family -- a process-local bag whose honest scope is the
 * process's own lifetime (the single-worker deployment, the test
 * suite, the local bootstrap), and whose documentation says so
 * plainly, because a multi-worker deployment pointing at
 * process-local storage would hold sessions no sibling worker can
 * see.
 *
 * The idle timeout is the sliding expiry the port's contract spells:
 * every save refreshes the entry's written-at reading, so activity
 * extends the session and only idleness ends one. The clock is the
 * extracted seam the auth family's own discipline taught ({@see
 * AuthClock}'s note: inline `microtime(true)` is arithmetic a
 * mutation run rewrites invisibly, so the reading lives in one named
 * place the suite can freeze) -- injectable as a Unix-seconds closure
 * and defaulting to the engine's own {@see time()}.
 *
 * An expired entry is purged at its own load ({@see self::load()})
 * -- a driver with no worker of its own answers expiry lazily, which
 * is the same posture the engine's own caches keep, and the timing is
 * deterministic under the frozen clock the suite pins, boundary
 * included (the timeout fully elapsed is expired, the last instant
 * before it is not). But the entry the family never revisits -- the
 * client that minted, was issued, and never returned -- has no load
 * to be purged at, so the save itself rides the extracted entries
 * fold ({@see SessionEntries}): the write and, at most once per its
 * cadence, the bounded sweep that retires the abandoned -- never a
 * background worker.
 */
final readonly class InMemorySessionAdapter implements SessionAdapterInterface
{
    /**
     * @param int                   $idleTimeout    the sliding expiry, in seconds
     *                                              (the policy's bounds hold the
     *                                              corridor)
     * @param null|(Closure(): int) $clock          the Unix-seconds clock,
     *                                              injectable so the suite can
     *                                              freeze it; defaults to the
     *                                              engine's own time()
     * @param SessionEntries        $sessionEntries the bag's own save-path fold
     *                                              ({@see SessionEntries}); the
     *                                              default is the engine's own
     */
    public function __construct(
        private int $idleTimeout,
        private ?Closure $clock = null,
        private SessionEntries $sessionEntries = new SessionEntries(),
    ) {
    }

    #[Override]
    public function load(string $id): ?array
    {
        $entry = $this->sessionEntries->entry($id);

        if ($entry === null) {
            return null;
        }

        if ($this->now() - $entry['writtenAt'] >= $this->idleTimeout) {
            $this->sessionEntries->destroy($id);

            return null;
        }

        return $entry['data'];
    }

    #[Override]
    public function save(string $id, array $data): void
    {
        $this->sessionEntries->saved($id, $data, $this->now(), $this->idleTimeout);
    }

    #[Override]
    public function destroy(string $id): void
    {
        $this->sessionEntries->destroy($id);
    }

    /**
     * The current reading of the injectable clock.
     */
    private function now(): int
    {
        return ($this->clock ?? time(...))();
    }
}
