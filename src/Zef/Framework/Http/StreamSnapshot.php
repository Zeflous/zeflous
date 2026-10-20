<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\StreamInterface;
use Throwable;

/** Captures a stream's full contents and restores its cursor, leaving the stream untouched. */
final class StreamSnapshot
{
    /** @return null|string the captured contents, or null when the stream could not be read or restored */
    public static function capture(StreamInterface $stream): ?string
    {
        try {
            $position = $stream->tell();
            $stream->rewind();
            $contents = $stream->getContents();
        } catch (Throwable) {
            if (isset($position)) {
                self::restore($stream, $position);
            }

            return null;
        }

        if (!self::restore($stream, $position)) {
            return null;
        }

        return $contents;
    }

    private static function restore(StreamInterface $stream, int $position): bool
    {
        try {
            $stream->seek($position);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
