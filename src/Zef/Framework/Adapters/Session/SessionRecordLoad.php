<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session store's load walk: the read boundary the port's load
 * verb answers -- the record's existence, its content, its decode,
 * and the sliding expiry's own judgment, the elapsed purged at its
 * own load and answered a fresh session (the miss the port's
 * contract spells: "not found, or found-but-expired" -- both
 * answer null, and no consumer ever needs to tell them apart).
 *
 * The walk is the adapter's own seam because the expiry judgment
 * is the load's whole second half: the in-memory driver's own load
 * carries the same two questions in one method, and the file
 * store's spelling keeps them together here, the adapter's verbs
 * staying the thin delegations the hexagonal boundary wants.
 */
final readonly class SessionRecordLoad
{
    /**
     * @param SessionRecordFiles   $sessionRecordFiles   the store's own layout fold
     * @param SessionRecordDecoder $sessionRecordDecoder the record's own reading half
     * @param int                  $idleTimeout          the sliding expiry, in seconds,
     *                                                   the same corridor the policy holds
     */
    public function __construct(
        private SessionRecordFiles $sessionRecordFiles,
        private SessionRecordDecoder $sessionRecordDecoder,
        private int $idleTimeout,
    ) {
    }

    /**
     * Loads one identifier's bag, or null when the identifier is
     * unknown or its idle timeout has fully elapsed.
     *
     * @param string $id  the session identifier
     * @param int    $now the load's own clock reading
     *
     * @return null|array<string, mixed> the stored bag, or null
     *
     * @throws InvalidArgumentException when the record cannot be
     *                                  read or does not decode
     */
    public function load(string $id, int $now): ?array
    {
        if (!$this->sessionRecordFiles->recordExists($id)) {
            return null;
        }

        $content = file_get_contents($this->sessionRecordFiles->recordPath($id));

        if ($content === false) {
            throw new InvalidArgumentException(
                "The session file store cannot read the record '{$id}'.",
            );
        }

        $record = $this->sessionRecordDecoder->decode($content, $id);

        if ($now - $record['writtenAt'] >= $this->idleTimeout) {
            $this->sessionRecordFiles->removeRecord($id);

            return null;
        }

        return $record['data'];
    }
}
