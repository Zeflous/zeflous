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
 * inside it. The vocabulary ships the five lanes with a live writer
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
 *
 * The vocabulary deliberately carries no `sessions` lane: the
 * session file driver is the storage port's named reservation, and
 * a lane joins the vocabulary in the pull request that lands its
 * first writer -- a lane without a writer is a directory nobody
 * provisions and nobody checks (the census-moment doctrine the
 * cookie-attribute fold spelled first).
 */
enum StorageLane: string
{
    case Logs = 'logs';
    case Cache = 'cache';
    case Temp = 'temp';
    case Uploads = 'uploads';
    case Replay = 'replay';

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
        };
    }
}
