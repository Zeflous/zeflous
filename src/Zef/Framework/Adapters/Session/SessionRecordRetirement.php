<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * One record's own retirement: the sweep's per-record judgement --
 * the record read, decoded, and retired when its own idle timeout
 * fully elapsed, the defect record (unreadable or undecodable)
 * retired into the quarantine lane instead, the walk carrying on.
 *
 * THE DEFECT'S OWN BOUNDARY (issue #788, finding 2): the first
 * spelling threw on the record the sweep could not decode, and the
 * refusal rode the save whose own record had already landed -- the
 * blast radius of one truncated file was the whole store. The
 * retirement answers the defect with the {@see
 * SessionRecordQuarantine} lane: the record leaves the record
 * namespace with its content preserved, the store keeps serving,
 * and the load path's own refusal stays loud for the session whose
 * defect it owns. The one honest exception is the record a sibling
 * worker retired between the scan and this fold's own read: a
 * vanished record is the retired state itself, never a quarantine.
 */
final readonly class SessionRecordRetirement
{
    /**
     * @param SessionRecordFiles      $sessionRecordFiles      the store's own layout fold
     * @param SessionRecordDecoder    $sessionRecordDecoder    the record's own reading half
     * @param SessionRecordQuarantine $sessionRecordQuarantine the lane the defect records retire into
     * @param int                     $idleTimeout             the sliding expiry, in seconds
     */
    public function __construct(
        private SessionRecordFiles $sessionRecordFiles,
        private SessionRecordDecoder $sessionRecordDecoder,
        private SessionRecordQuarantine $sessionRecordQuarantine,
        private int $idleTimeout,
    ) {
    }

    /**
     * Retires one record when its own timeout fully elapsed: the
     * same boundary the load path refuses at (the timeout fully
     * elapsed is expired, the last instant before it is not).
     *
     * @param string $id  the record's own identifier, the scan's answer
     * @param int    $now the save's own clock reading
     */
    public function retire(string $id, int $now): void
    {
        $record = $this->decodedOrRetired($id);

        if ($record === null) {
            return;
        }

        if ($now - $record['writtenAt'] >= $this->idleTimeout) {
            $this->sessionRecordFiles->removeRecord($id);
        }
    }

    /**
     * The record's decoded half, or null once the defect is retired
     * into the quarantine lane -- the defect record never takes the
     * walk with it, the blast radius the record's own boundary pays.
     * The unreadable record a sibling worker retired through the
     * window answers null untouched (the re-check answers what the
     * read's own false could not explain), the record that exists
     * and cannot be read is quarantined -- the move never needs the
     * bytes it cannot read.
     *
     * @param string $id the record's own identifier
     *
     * @return null|array{data: array<string, mixed>, writtenAt: int} the decoded record, or null once handled
     */
    private function decodedOrRetired(string $id): ?array
    {
        $content = file_get_contents($this->sessionRecordFiles->recordPath($id));

        if (\is_string($content)) {
            try {
                return $this->sessionRecordDecoder->decode($content, $id);
            } catch (InvalidArgumentException) {
                $this->sessionRecordQuarantine->quarantine($id);
            }
        } elseif ($this->sessionRecordFiles->recordExists($id)) {
            $this->sessionRecordQuarantine->quarantine($id);
        }

        return null;
    }
}
