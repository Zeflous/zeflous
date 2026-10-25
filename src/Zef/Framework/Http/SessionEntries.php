<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session port's in-memory entries: the bag's own save-path fold
 * -- one write, and the cadenced sweep that rides it ({@see
 * InMemorySessionAdapter} hands every save here).
 *
 * The write half is the port's own contract: an entry is the bag's
 * identifier => [data, writtenAt] cell, and every save refreshes the
 * written-at reading, so activity extends the session and only
 * idleness ends one ({@see InMemorySessionAdapter} owns the sliding
 * expiry's lazy refusal at load; this fold owns the write side).
 *
 * The sweep half retires the abandoned entry no load will ever
 * revisit -- the client that minted, was issued, and never returned.
 * The driver's own lazy purge answers the entry the family revisits:
 * an expired identifier is refused at its own load. But the abandoned
 * entry has no load to be refused at, and a driver with no worker of
 * its own would otherwise grow one abandoned entry per abandonment
 * (the round-3 audit's R3-4: cookie-less traffic that mints and never
 * returns). The sweep is the bounded amortised answer: it runs at
 * most once per {@see self::SWEEP_SECONDS}, riding a save that was
 * already paying a write -- one array walk per cadence, never a
 * background worker -- and the boundary's untouched-mint gate
 * ({@see SessionSettlement}) keeps the walks rare, because only a
 * session the handler wrote into is ever saved at all.
 *
 * The cadence and the idle timeout are separate clocks: the cadence
 * bounds how often the walk runs, the idle timeout decides which
 * entries it takes -- an entry is swept exactly when its own timeout
 * fully elapsed, the same boundary the load path refuses at (the
 * timeout fully elapsed is expired, the last instant before it is
 * not).
 */
final class SessionEntries
{
    /**
     * The sweep cadence: the save-path pruning runs at most once per
     * this many seconds.
     */
    private const int SWEEP_SECONDS = 60;

    /**
     * The stored sessions: identifier => [data, writtenAt].
     *
     * @var array<string, array{data: array<string, mixed>, writtenAt: int}>
     */
    private array $entries = [];

    /** The reading the last sweep ran at. */
    private int $lastSweepAt = 0;

    /**
     * The bag after one save: the entry written, the abandoned swept.
     *
     * @param string               $id          the session identifier
     * @param array<string, mixed> $data        the bag the settlement persists
     * @param int                  $now         the save's own clock reading
     * @param int                  $idleTimeout the sliding expiry, in seconds
     */
    public function saved(string $id, array $data, int $now, int $idleTimeout): void
    {
        $this->entries[$id] = ['data' => $data, 'writtenAt' => $now];

        if ($now - $this->lastSweepAt < self::SWEEP_SECONDS) {
            return;
        }

        $this->lastSweepAt = $now;

        $this->entries = array_filter(
            $this->entries,
            static fn (array $entry): bool => $now - $entry['writtenAt'] < $idleTimeout,
        );
    }

    /**
     * One identifier's cell, or null when the bag carries none -- the
     * driver's own expiry judgement reads the written-at reading.
     *
     * @return null|array{data: array<string, mixed>, writtenAt: int}
     */
    public function entry(string $id): ?array
    {
        return $this->entries[$id] ?? null;
    }

    /**
     * Retires one identifier's entry -- the bag's own cell, dropped in
     * place.
     */
    public function destroy(string $id): void
    {
        unset($this->entries[$id]);
    }
}
