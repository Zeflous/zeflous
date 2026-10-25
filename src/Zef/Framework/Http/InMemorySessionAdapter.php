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
 * -- a driver with no worker of its own answers expiry lazily first,
 * which is the same posture the engine's own caches keep, and the
 * timing is deterministic under the frozen clock the suite pins,
 * boundary included (the timeout fully elapsed is expired, the last
 * instant before it is not). But the entry the family never revisits
 * -- the client that minted, was issued, and never returned -- has
 * no load to be purged at, so the save path also sweeps the whole
 * bag at most once per {@see self::SWEEP_SECONDS} (the round-3
 * audit's R3-4: cookie-less traffic would otherwise grow the bag one
 * abandoned entry per request, and only the sweep retires the
 * abandoned). The sweep is the bounded amortised pass -- one array
 * walk per cadence, riding a save that was already paying a write --
 * never a background worker, and the boundary's untouched-mint gate
 * ({@see SessionSettlement}) keeps the walks rare: only sessions the
 * handler wrote into are ever saved at all.
 */
final class InMemorySessionAdapter implements SessionAdapterInterface
{
    /**
     * The sweep cadence: the save-path pruning of expired entries runs
     * at most once per this many seconds.
     */
    private const int SWEEP_SECONDS = 60;

    /**
     * The stored sessions: identifier => [data, writtenAt].
     *
     * @var array<string, array{data: array<string, mixed>, writtenAt: int}>
     */
    private array $sessions = [];

    /** The last reading the save-path sweep ran at. */
    private int $lastSweepAt = 0;

    /**
     * @param int                   $idleTimeout the sliding expiry, in seconds
     *                                           (the policy's bounds hold the
     *                                           corridor)
     * @param null|(Closure(): int) $clock       the Unix-seconds clock,
     *                                           injectable so the suite can
     *                                           freeze it; defaults to the
     *                                           engine's own time()
     */
    public function __construct(
        private readonly int $idleTimeout,
        private readonly ?Closure $clock = null,
    ) {
    }

    #[Override]
    public function load(string $id): ?array
    {
        $entry = $this->sessions[$id] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($this->now() - $entry['writtenAt'] >= $this->idleTimeout) {
            unset($this->sessions[$id]);

            return null;
        }

        return $entry['data'];
    }

    #[Override]
    public function save(string $id, array $data): void
    {
        $now = $this->now();
        $this->sessions[$id] = ['data' => $data, 'writtenAt' => $now];
        $this->sweep($now);
    }

    #[Override]
    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }

    /**
     * The current reading of the injectable clock.
     */
    private function now(): int
    {
        return ($this->clock ?? time(...))();
    }

    /**
     * The save-path sweep: at most once per cadence, every entry whose
     * idle timeout fully elapsed is pruned -- the abandoned entry no
     * load will ever revisit, retired by a save that was already
     * paying a write.
     */
    private function sweep(int $now): void
    {
        if ($now - $this->lastSweepAt < self::SWEEP_SECONDS) {
            return;
        }

        $this->lastSweepAt = $now;

        foreach ($this->sessions as $id => $entry) {
            if ($now - $entry['writtenAt'] >= $this->idleTimeout) {
                unset($this->sessions[$id]);
            }
        }
    }
}
