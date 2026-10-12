<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * The payload stream's read position across an upload move: captured
 * before the copy (a rewind included, so the whole payload moves) and
 * restored on failure (so a retry reads the same bytes).
 *
 * The restore is best-effort by design -- the original failure must
 * propagate, never be replaced by a restoration failure.
 */
final class UploadedPosition
{
    /**
     * Captures the current read position and rewinds the stream.
     *
     * @return null|int the position to restore, null when the stream
     *                  reports itself unseekable
     */
    public static function capture(StreamInterface $stream): ?int
    {
        if (!$stream->isSeekable()) {
            return null;
        }

        $position = $stream->tell();
        $stream->rewind();

        return $position;
    }

    /**
     * Restores the captured read position on the failure path.
     */
    public static function restore(StreamInterface $stream, int $position): void
    {
        try {
            $stream->seek($position);
        } catch (Throwable) {
            // Intentionally empty: restoring the read position is
            // best-effort cleanup on the failure path -- the original
            // failure must propagate, not be replaced by this one.
        }
    }
}
