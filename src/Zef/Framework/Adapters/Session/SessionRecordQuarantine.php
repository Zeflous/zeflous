<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session store's quarantine lane: the `.quarantine/`
 * subdirectory the sweep retires a record it cannot decode or
 * cannot read into -- the defect the operator's story keeps,
 * content preserved verbatim, out of the record namespace the
 * store's own walks and loads answer for (issue #788, finding 2:
 * the first spelling let ONE undecodable record brick every
 * save-bearing request on the store -- the sweep's walk threw on
 * the record it could not decode, and the refusal rode the save
 * whose own record had already landed; the blast radius of one
 * truncated file was the whole store, exactly the store-wide
 * failure the strict census was never asked to spell).
 *
 * THE POSTURE THE LANE CARRIES is the delivery store's own
 * quarantine idiom ({@see \Zef\Framework\Adapters\Event\DeliveryQuarantineLane}):
 * the defect is never silently stepped over -- it is retired, with
 * its content, into a named lane the operator can read -- and the
 * record namespace it leaves is the one the store keeps serving.
 * The load path's own refusal stays loud for a defect the sweep
 * has not walked past yet; once quarantined, the record answers the
 * miss every retired record answers. The lane is a leading-dot
 * directory: the scan's own grammar skips dot-prefixed names and
 * directories both, so the lane never enters a walk, exactly like
 * the staging files and lock sidecars it sits beside.
 */
final readonly class SessionRecordQuarantine
{
    /** The quarantine lane's directory name: a leading dot, the scans' own blind spot. */
    private const string LANE = '.quarantine';

    /**
     * @param string $directory the store root the directory fold
     *                          already resolved ({@see SessionStoreDirectory})
     */
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * Retires one defect record out of the record namespace, its
     * content preserved verbatim in the lane -- the move the sweep
     * answers a record it cannot decode or cannot read with,
     * never the refusal that would fail the unrelated saves whose
     * own records already landed.
     *
     * @param string $id the defect record's own identifier
     *
     * @throws InvalidArgumentException when the lane cannot be
     *                                  opened or the record cannot be
     *                                  moved into it
     */
    public function quarantine(string $id): void
    {
        $lane = $this->directory . \DIRECTORY_SEPARATOR . self::LANE;

        if (!is_dir($lane) && !mkdir($lane, 0o700)) {
            throw new InvalidArgumentException(
                'The session file store cannot open its quarantine lane.',
            );
        }

        $record = $this->directory . \DIRECTORY_SEPARATOR . $id . SessionRecordFiles::EXTENSION;

        if (!rename($record, $lane . \DIRECTORY_SEPARATOR . $id . SessionRecordFiles::EXTENSION)) {
            throw new InvalidArgumentException(
                "The session file store cannot quarantine the record '{$id}'.",
            );
        }
    }
}
