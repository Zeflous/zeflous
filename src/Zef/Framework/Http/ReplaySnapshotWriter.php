<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Throwable;

/** Writes one exclusive, owner-only replay snapshot and removes partial files on failure. */
final class ReplaySnapshotWriter
{
    public static function write(string $directory, string $id, string $json): void
    {
        $destination = $directory . \DIRECTORY_SEPARATOR . $id . '.json';
        $stream = fopen($destination, 'xb');
        if ($stream === false) {
            throw new ReplaySnapshotException('Unable to create an exclusive replay snapshot.');
        }

        $locked = false;

        try {
            if (!chmod($destination, 0o600)) {
                throw new ReplaySnapshotException('Unable to secure the replay snapshot file.');
            }

            if (!flock($stream, \LOCK_EX)) {
                throw new ReplaySnapshotException('Unable to lock the replay snapshot file.');
            }

            $locked = true;

            self::writeAll($stream, $json . "\n");
        } catch (Throwable $throwable) {
            if ($locked) {
                flock($stream, \LOCK_UN);
            }

            fclose($stream);

            if (!unlink($destination)) {
                throw new ReplaySnapshotException('Unable to remove an incomplete replay snapshot.', 0, $throwable);
            }

            throw $throwable;
        }

        flock($stream, \LOCK_UN);
        fclose($stream);
    }

    /**
     * The byte-wise remainder loop: the cursor advances by the BYTES the
     * stream actually accepted, and the remainder the next call ships is
     * the byte-tail from that exact offset (`unpack()`'s own offset --
     * deliberately not `substr()` arithmetic, which the coding standard's
     * multibyte-safe fixer would silently turn into character arithmetic).
     * A short write on a multibyte snapshot therefore resumes at the
     * snapshot's NEXT BYTE, never at its next character: the
     * character-offset walk this loop once kept could skip the trailing
     * half of a partially-written multibyte sequence and exit
     * "successfully" on a torn snapshot.
     *
     * @param resource $stream
     */
    private static function writeAll($stream, string $contents): void
    {
        $offset = 0;

        while (isset($contents[$offset])) {
            $unpacked = unpack('a*', $contents, $offset);
            $remainder = '';

            if (\is_array($unpacked) && isset($unpacked[1]) && \is_string($unpacked[1])) {
                $remainder = $unpacked[1];
            }

            $written = fwrite($stream, $remainder);

            if ($written === false || $written === 0) {
                throw new ReplaySnapshotException('Unable to write a complete replay snapshot.');
            }

            $offset += $written;
        }
    }
}
