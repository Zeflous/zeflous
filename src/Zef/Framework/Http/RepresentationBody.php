<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\StreamInterface;

/**
 * Reads a response representation for inspection while leaving the
 * stream the way every later consumer expects it: the whole body is
 * consumed, and a seekable stream's cursor is restored to the start
 * afterwards (the audit #303 regression in the reference lineage --
 * `(string)` leaves a seekable stream at EOF, and the shared stream
 * flows on to the emitter, which must still ship the full
 * representation on the cache-miss path). A non-seekable stream simply
 * yields its payload; there is no cursor to restore.
 */
final class RepresentationBody
{
    /**
     * Answers the whole body, restoring the cursor of a seekable stream.
     */
    public static function read(StreamInterface $stream): string
    {
        $content = (string) $stream;

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $content;
    }
}
