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
 * The cadence and the idle timeout are separate clocks: the
 * cadence bounds how often the walk runs (at most once per {@see
 * self::SWEEP_SECONDS}, riding a save that was already paying a
 * write -- one directory walk per cadence, never a background
 * worker), the idle timeout decides which records it takes. A
 * record is swept exactly when its own timeout fully elapsed, the
 * same boundary the load path refuses at (the timeout fully
 * elapsed is expired, the last instant before it is not).
 *
 * The walk is fail-closed: a record that does not decode is a
 * defect the operator must hear about (the refusal names the
 * record), never a file the sweep quietly steps over -- an
 * undecodable record the sweep skipped would linger forever, seen
 * by no load and retired by no pass. The same posture the durable
 * delivery store's due-selection scan spells for its own records.
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

    /** The reading the last sweep ran at. */
    private int $lastSweepAt = 0;

    /**
     * @param SessionRecordScan    $sessionRecordScan    the store's own scan fold
     * @param SessionRecordFiles   $sessionRecordFiles   the store's own layout fold
     * @param SessionRecordDecoder $sessionRecordDecoder the record's own reading half
     * @param int                  $idleTimeout          the sliding expiry, in seconds,
     *                                                   the same corridor the policy holds
     */
    public function __construct(
        private readonly SessionRecordScan $sessionRecordScan,
        private readonly SessionRecordFiles $sessionRecordFiles,
        private readonly SessionRecordDecoder $sessionRecordDecoder,
        private readonly int $idleTimeout,
    ) {
    }

    /**
     * The save's own sweep question: the cadence's answer first (a
     * save inside the cadence rides no walk), then the bounded
     * walk -- every record file read, judged against the timeout,
     * the elapsed retired.
     *
     * @param int $now the save's own clock reading
     */
    public function rideSave(int $now): void
    {
        if ($now - $this->lastSweepAt < self::SWEEP_SECONDS) {
            return;
        }

        $this->lastSweepAt = $now;

        foreach ($this->sessionRecordScan->recordFilenames() as $filename) {
            $this->sweepRecord($this->sessionRecordFiles->recordIdOf($filename), $now);
        }
    }

    /**
     * One record's own sweep judgement: read, decode, and retire
     * when the timeout fully elapsed.
     *
     * @param string $id  the record's own identifier, the scan's answer
     * @param int    $now the save's own clock reading
     *
     * @throws InvalidArgumentException when the record cannot be
     *                                  read or does not decode
     */
    private function sweepRecord(string $id, int $now): void
    {
        $content = file_get_contents($this->sessionRecordFiles->recordPath($id));

        if ($content === false) {
            if (!$this->sessionRecordFiles->recordExists($id)) {
                return;
            }

            throw new InvalidArgumentException(
                "The session file store cannot read the record '{$id}'.",
            );
        }

        $record = $this->sessionRecordDecoder->decode($content, $id);

        if ($now - $record['writtenAt'] < $this->idleTimeout) {
            return;
        }

        $this->sessionRecordFiles->removeRecord($id);
    }
}
