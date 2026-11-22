<?php

declare(strict_types=1);

namespace Zef\Framework\Storage;

/**
 * The storage lane vocabulary the `files/**` route map spells: the
 * one place every lane's canonical directory name and one-line
 * purpose lives, so no collaborator -- resolver, renderer, or the
 * repository skeleton's own pin -- re-spells it.
 *
 * A lane names a KIND of on-disk artifact the framework or its host
 * writes, never a specific file: the layout answers where a lane's
 * directory is, the writer that owns the artifact names the file
 * inside it. The vocabulary ships the seven lanes, every one with
 * its live writer today:
 *
 * - `logs` -- the structured JSONL records the error and
 *   slow-request lanes write (the deployment's one sink).
 * - `cache` -- the compiled artifacts the configuration and
 *   container AOT lanes dump for a fast boot.
 * - `temp` -- the transient scratch a process may leave behind
 *   between requests without anyone mourning it: the writer
 *   with its cadenced sweep (`temp.store`, the retention the
 *   host spells) landed over it, closing the reservation the
 *   priorities table's own P1 row carried.
 * - `uploads` -- the inbound files a host's uploaded-file moves
 *   land in: the lane's validated writer (`uploads.store`, the
 *   types the host spells) over its fail-closed admission -- the
 *   named reservation this docblock carried honestly through the
 *   layout's own lifetime, closed by the pull request that landed
 *   the writer (issue #788, finding 9's honesty held both ways: the
 *   lane named the reservation while it held no writer, and the
 *   bullet names the writer it now holds).
 * - `replay` -- the error lane's private request snapshots.
 * - `sessions` -- the session file driver's identifier-keyed bags.
 * - `queue` -- the durable event delivery store's entries and
 *   books (the wired default the `events.deferred` knob spells).
 *
 * The `sessions` lane joined the vocabulary in the pull request
 * that landed its first writer ({@see \Zef\Framework\Adapters\Session\FileSessionAdapter})
 * -- the census-moment doctrine the cookie-attribute fold spelled
 * first, honoured here by its own answer: the vocabulary carried no
 * `sessions` lane while the driver was a named reservation, and the
 * lane and the writer landed together, so no directory exists that
 * nobody provisions and nobody checks. The `queue` lane answers the
 * same doctrine with the same shape ({@see \Zef\Framework\EventStorageWiring}):
 * the file delivery store kept its own directory posture while the
 * wired default was a named reservation, and the lane, the knob and
 * the reader landed together. The `uploads` and `temp` lanes are the
 * doctrine's own other half, spelled honestly in the priorities
 * record before they closed: NAMED RESERVATIONS the vocabulary
 * carried through the layout's own lifetime because the
 * reservations were the roadmap's own recorded open slices -- the
 * writers landing later (#908 and its successor), over the
 * directories, walk answers and skeleton pins the reservations
 * already carried, never a lane the vocabulary grew to flatter a
 * writer it did not hold.
 */
enum StorageLane: string
{
    case Logs = 'logs';
    case Cache = 'cache';
    case Temp = 'temp';
    case Uploads = 'uploads';
    case Replay = 'replay';
    case Sessions = 'sessions';
    case Queue = 'queue';

    /**
     * The lane's canonical directory spelling under the storage
     * root: the one name the resolver joins and the repository
     * skeleton carries, lowercase and single-word by construction
     * (the enum's own backing).
     */
    public function spelling(): string
    {
        return $this->value;
    }

    /**
     * The lane's one-line purpose, the sentence the inspector's
     * renderers print beside the resolved path so an operator
     * reading the map in any environment reads what belongs where
     * without opening the decision record.
     */
    public function purpose(): string
    {
        return match ($this) {
            self::Logs => 'the structured JSONL records the error and slow-request lanes write',
            self::Cache => 'the compiled configuration and container artifacts a fast boot reads',
            self::Temp => 'the transient scratch a process may leave between requests',
            self::Uploads => 'the inbound files the uploaded-file moves land in',
            self::Replay => "the error lane's private request snapshots",
            self::Sessions => "the session file driver's identifier-keyed bags",
            self::Queue => "the durable event delivery store's entries and books",
        };
    }
}
