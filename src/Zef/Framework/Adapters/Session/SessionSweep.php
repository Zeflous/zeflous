<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session store's cadenced sweep: the fold that retires the
 * abandoned record no load will ever revisit -- the client that
 * minted, was issued, and never returned -- replayed over the
 * filesystem exactly as the in-memory driver's own entries fold
 * spells it ({@see \Zef\Framework\Http\SessionEntries}).
 *
 * The cadence and the idle timeout are separate clocks: the cadence
 * bounds how often the walk runs (at most once per {@see
 * self::SWEEP_SECONDS}, riding a save that was already paying a
 * write -- one directory walk per cadence, never a background
 * worker), the idle timeout decides which records it takes. A
 * record is swept exactly when its own timeout fully elapsed, the
 * same boundary the load path refuses at (the timeout fully
 * elapsed is expired, the last instant before it is not).
 *
 * THE CADENCE IS THE STORE'S OWN MEMORY ({@see SessionSweepCadence},
 * issue #788, finding 2): the last-sweep reading is persisted in
 * the store itself, because the adapter is composed per request --
 * an instance field would reset with every composition, and every
 * save-bearing request would pay a full store walk while the
 * sixty-second cadence promised the walk once a minute. The
 * marker's absence answers zero, the walk the store pays -- the
 * cadence is a cost bound, never a correctness one.
 *
 * THE DEFECT RECORD IS QUARANTINED, NEVER THE STORE'S OWN REFUSAL
 * ({@see SessionRecordQuarantine}, issue #788, finding 2): a record
 * that does not decode -- or cannot be read at all -- is retired
 * out of the record namespace with its content preserved in the
 * lane, and the walk carries on. The first spelling threw on the
 * defect, and ONE truncated file bricked every save-bearing request
 * on the store: the refusal rode the save whose own record had
 * already landed, the blast radius of a single defect record was the
 * whole store. The load path's own refusal stays loud for the
 * session whose defect it owns; the sweep's quarantine is the blast
 * radius the record's own boundary pays -- the store keeps serving,
 * the operator's story preserved verbatim in the lane.
 *
 * The one honest exception is the record a sibling worker retired
 * between the scan and this fold's own read: a vanished record is
 * the retired state itself (the re-check answers what the read's
 * own false could not explain), never a refusal -- the sweep and
 * the retirement race is the multi-worker store's own arithmetic.
 */
final class SessionSweep
{
    /**
     * The sweep cadence: the save-riding pruning runs at most once
     * per this many seconds.
     */
    private const int SWEEP_SECONDS = 60;

    /**
     * @param SessionRecordScan       $sessionRecordScan       the store's own scan fold
     * @param SessionRecordFiles      $sessionRecordFiles      the store's own layout fold
     * @param SessionRecordDecoder    $sessionRecordDecoder    the record's own reading half
     * @param int                     $idleTimeout             the sliding expiry, in seconds,
     *                                                         the same corridor the policy holds
     * @param SessionSweepCadence     $sessionSweepCadence     the cadence's own store-persisted memory
     * @param SessionRecordQuarantine $sessionRecordQuarantine the lane the defect records retire into
     */
    public function __construct(
        private readonly SessionRecordScan $sessionRecordScan,
        private readonly SessionRecordFiles $sessionRecordFiles,
        private readonly SessionRecordDecoder $sessionRecordDecoder,
        private readonly int $idleTimeout,
        private readonly SessionSweepCadence $sessionSweepCadence,
        private readonly SessionRecordQuarantine $sessionRecordQuarantine,
    ) {
    }

    /**
     * The save's own sweep question: the cadence's answer first (a
     * save inside the cadence rides no walk), then the bounded
     * walk -- every record file read, judged against the timeout,
     * the elapsed retired, the defect quarantined.
     *
     * @param int $now the save's own clock reading
     */
    public function rideSave(int $now): void
    {
        if ($now - $this->sessionSweepCadence->lastRanAt() < self::SWEEP_SECONDS) {
            return;
        }

        $this->sessionSweepCadence->ranAt($now);

        foreach ($this->sessionRecordScan->recordFilenames() as $filename) {
            $this->sweepRecord($this->sessionRecordFiles->recordIdOf($filename), $now);
        }
    }

    /**
     * One record's own sweep judgement: read, decode, and retire
     * when the timeout fully elapsed -- the record that cannot be
     * read or does not decode retired into the quarantine lane
     * instead, the walk carrying on.
     *
     * @param string $id  the record's own identifier, the scan's answer
     * @param int    $now the save's own clock reading
     *
     * @throws InvalidArgumentException when the quarantine lane
     *                                  itself refuses -- the store's
     *                                  own filesystem failure, never
     *                                  a record's defect
     */
    private function sweepRecord(string $id, int $now): void
    {
        $content = file_get_contents($this->sessionRecordFiles->recordPath($id));

        if ($content === false) {
            if (!$this->sessionRecordFiles->recordExists($id)) {
                return;
            }

            $this->sessionRecordQuarantine->quarantine($id);

            return;
        }

        try {
            $record = $this->sessionRecordDecoder->decode($content, $id);
        } catch (InvalidArgumentException) {
            $this->sessionRecordQuarantine->quarantine($id);

            return;
        }

        if ($now - $record['writtenAt'] < $this->idleTimeout) {
            return;
        }

        $this->sessionRecordFiles->removeRecord($id);
    }
}
