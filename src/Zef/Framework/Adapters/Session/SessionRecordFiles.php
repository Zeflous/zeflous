<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session store's file layout fold: which file one
 * identifier's record is, and the removal that retires one. Every
 * record is one JSON file named by the session's own identifier
 * plus the extension -- the identifier's alphabet is base64url
 * ({@see \Zef\Framework\Http\SessionId::GRAMMAR}), so the name the
 * identifier spells is a filename the filesystem carries verbatim,
 * no transliteration needed -- and a file the store cannot name it
 * never exists for it. Which files the store's own walks SEE is
 * the scan fold's own question ({@see SessionRecordScan}), the
 * naming and the seeing two folds for the same reason the delivery
 * store's layout and due-selection always were.
 */
final readonly class SessionRecordFiles
{
    /** The record file's extension: the JSON spelling the codec owns. */
    public const string EXTENSION = '.json';

    /**
     * @param string $directory the store root the directory fold
     *                          already resolved ({@see SessionStoreDirectory})
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * One identifier's record path: the identifier's own spelling as
     * the filename, the extension the record's kind.
     */
    public function recordPath(string $id): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . $id . self::EXTENSION;
    }

    /**
     * The identifier one record filename spells: the extension's
     * own bytes cut from the tail (the extension is ASCII, so the
     * byte-wise cut is the honest cut for every filename, a foreign
     * multibyte name included -- its refusal is the codec's own
     * business, never this fold's).
     */
    public function recordIdOf(string $filename): string
    {
        return mb_substr($filename, 0, -mb_strlen(self::EXTENSION));
    }

    /**
     * Whether one identifier's record file exists: a directory named
     * like a record answers false -- the miss is the truth a load
     * answers a fresh session with, never a refusal.
     */
    public function recordExists(string $id): bool
    {
        return is_file($this->recordPath($id));
    }

    /**
     * Removes one identifier's record file -- idempotent, and
     * honest under concurrency: a record already removed is the
     * destroyed state itself, never a refusal, and a record a
     * sibling worker retired between the existence check and the
     * unlink is that same state arrived at through the window (the
     * re-check answers what the unlink's own false could not
     * explain -- a different existence question than the gate's,
     * because the gate asked before the unlink and the window is
     * exactly what can have changed since). The session file store
     * is multi-worker by purpose -- the cross-worker visibility is
     * the whole reason a deployment spells the store knob `file`
     * -- so the retirement idiom is spelled for the workers that
     * race, not for the single process that never does. A
     * directory named like a record is the miss the load path
     * already answers ({@see self::recordExists()}), so the
     * removal treats it as one: foreign material, never a session.
     *
     * @throws InvalidArgumentException when the record file exists
     *                                  and cannot be removed
     */
    public function removeRecord(string $id): void
    {
        $destination = $this->recordPath($id);

        if (!is_file($destination)) {
            return;
        }

        if (!unlink($destination) && file_exists($destination)) {
            throw new InvalidArgumentException(
                "The session file store cannot remove the record '{$id}'.",
            );
        }
    }
}
