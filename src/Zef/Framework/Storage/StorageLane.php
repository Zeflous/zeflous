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
 * inside it. The vocabulary ships the six lanes with a live writer
 * or a named reservation today:
 *
 * - `logs` -- the structured JSONL records the error and
 *   slow-request lanes write (the deployment's one sink).
 * - `cache` -- the compiled artifacts the configuration and
 *   container AOT lanes dump for a fast boot.
 * - `temp` -- the transient scratch a process may leave behind
 *   between requests without anyone mourning it.
 * - `uploads` -- the inbound files a host's uploaded-file moves
 *   land in, the lane `UploadedTarget` answers for.
 * - `replay` -- the error lane's private request snapshots.
 * - `sessions` -- the session file driver's identifier-keyed bags.
 *
 * The `sessions` lane joined the vocabulary in the pull request
 * that landed its first writer ({@see \Zef\Framework\Adapters\Session\FileSessionAdapter})
 * -- the census-moment doctrine the cookie-attribute fold spelled
 * first, honoured here by its own answer: the vocabulary carried no
 * `sessions` lane while the driver was a named reservation, and the
 * lane and the writer landed together, so no directory exists that
 * nobody provisions and nobody checks.
 */
enum StorageLane: string
{
    case Logs = 'logs';
    case Cache = 'cache';
    case Temp = 'temp';
    case Uploads = 'uploads';
    case Replay = 'replay';
    case Sessions = 'sessions';

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
        };
    }
}
