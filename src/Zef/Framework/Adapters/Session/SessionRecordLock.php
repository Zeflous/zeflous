<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session record's read-modify-write lane: the sidecar lock the
 * file driver holds for the whole span between one request's load
 * and its save -- the native `files` handler's own semantics
 * (`flock` over the session file for the request's duration), the
 * issue #781 finding's answer: the port's load and save are two
 * verbs with an entire request between them, and two overlapping
 * requests on one session (two tabs, a double-submit, an AJAX
 * poller) interleaved their read-modify-writes last-write-wins --
 * one request's bag silently replacing the sibling's, writes lost
 * without a trace.
 *
 * THE SIDECAR, not the record file itself: the lane locks a
 * dedicated `.<id>.lock` companion ({@see SessionRecordFiles::
 * lockPath()}) because the record's own write is the one-rename
 * discipline ({@see SessionRecordStaging}) -- a `flock` held on the
 * record's inode would be released by the very rename that
 * finalises the write (the next opener addresses a NEW inode no
 * one holds), so the record file itself cannot carry the lane.
 * The sidecar is a dot-file the store's own walks never see
 * ({@see SessionRecordScan}), stranded-forever material exactly
 * like the staging dot-files a crash strands (an operator may
 * sweep dot-files while no writer runs) -- the companion carries
 * no bytes at all, forever: an empty file the directory's own
 * owner-only posture already guards.
 *
 * THE BLOCKING posture: `LOCK_EX` waits, exactly as the native
 * handler's own lock does and exactly as this repository's own
 * `flock` lanes spell it ({@see \Zef\Framework\Logging\
 * JsonLineWriter}, {@see \Zef\Framework\Http\
 * ReplaySnapshotWriter}) -- a sibling request on the same session
 * waits for the lane, which is the entire point: the second
 * request's read-modify-write begins over the first one's write.
 *
 * THE ONE LANE per driver instance: the span between the load and
 * the save is the request, and one instance serves one
 * read-modify-write at a time -- the native handler's own
 * posture. The same identifier's second acquire is the lane
 * continuing (the load that began the request, the save that
 * ends it); a DIFFERENT identifier's acquire while a lane is held
 * is the multi-lane shape the in-memory driver owns and this
 * driver refuses ({@see \Zef\Framework\Http\
 * InMemorySessionAdapter} -- its map answers every identifier at
 * once; this lane answers one request). The refusal is the
 * adapter-port grammar's own construction-time {@see
 * InvalidArgumentException}, the adapters lane's posture.
 *
 * The lane is released at the save or destroy that ends the
 * request, idempotently; a process that dies mid-span releases it
 * by the engine's own handle teardown, the same guarantee every
 * `flock` carries -- the lane never outlives the process that
 * holds it.
 */
final class SessionRecordLock
{
    /** The identifier whose lane is held, when one is. */
    private ?string $heldId = null;

    /** @var null|resource the held lane's own companion handle, when one is */
    private $heldHandle;

    /**
     * @param SessionRecordFiles $sessionRecordFiles the store's own layout
     *                                               fold -- the sidecar's naming
     *                                               belongs to which file one
     *                                               identifier's lane is
     */
    public function __construct(
        private readonly SessionRecordFiles $sessionRecordFiles,
    ) {
    }

    /**
     * Takes (or continues) one identifier's read-modify-write lane:
     * the sidecar opened without truncation, created when absent,
     * the exclusive lock waited for -- the blocking posture the
     * native handler and this repository's own `flock` lanes carry.
     *
     * A lane already held for the SAME identifier is the lane
     * continuing -- the save or destroy that ends the request
     * re-acquires the very lane its load began. A lane held for a
     * DIFFERENT identifier is the multi-lane shape this driver
     * refuses: one instance, one request, one read-modify-write.
     *
     * @param string $id the session identifier whose lane is taken
     *
     * @throws InvalidArgumentException when the sidecar cannot be
     *                                  opened or locked, or a different
     *                                  identifier's lane is already held
     */
    public function acquire(string $id): void
    {
        if ($this->heldId === null) {
            $this->heldId = $id;
            $this->heldHandle = $this->lockedSidecarHandle($id);

            return;
        }

        if ($this->heldId === $id) {
            return;
        }

        $this->refuseForeignLane($this->heldId, $id);
    }

    /**
     * Releases the held lane, idempotently: the unlock and the
     * close are the engine's own teardown when the process dies
     * mid-span, and the explicit release the save or destroy that
     * ends the request answers -- a driver with no lane held
     * answers the release as the no-op it is.
     */
    public function release(): void
    {
        $handle = $this->heldHandle;

        if ($handle === null) {
            return;
        }

        flock($handle, \LOCK_UN);
        fclose($handle);
        $this->heldHandle = null;
        $this->heldId = null;
    }

    /**
     * The one-lane posture's own refusal: the interleave named for
     * both of its sides -- the lane this instance holds and the
     * identifier it was asked -- the native handler's own posture
     * spelled as the driver's own honest scope.
     *
     * @throws InvalidArgumentException always: this arm IS the refusal
     */
    private function refuseForeignLane(string $heldId, string $id): never
    {
        throw new InvalidArgumentException(
            'The session file store holds one read-modify-write lane at a time'
            . " (held: '{$heldId}', asked: '{$id}').",
        );
    }

    /**
     * Opens one identifier's sidecar companion and waits for its
     * exclusive lock: the engine's own blocking `flock` over the
     * empty companion file, created when absent, never truncated --
     * the lane's whole machinery in one fold, so the refusal arms
     * name their own vocabulary (the companion that cannot open,
     * the lane that cannot be taken).
     *
     * @return resource the locked companion's own handle
     *
     * @throws InvalidArgumentException when the companion cannot be
     *                                  opened or its lock not taken
     */
    private function lockedSidecarHandle(string $id)
    {
        $sidecar = $this->sessionRecordFiles->lockPath($id);
        $opened = fopen($sidecar, 'cb');

        if ($opened === false) {
            throw new InvalidArgumentException(
                "The session file store cannot open the read-modify-write lane '{$id}'.",
            );
        }

        if (flock($opened, \LOCK_EX) === false) {
            fclose($opened);

            throw new InvalidArgumentException(
                "The session file store cannot lock the read-modify-write lane '{$id}'.",
            );
        }

        return $opened;
    }
}
