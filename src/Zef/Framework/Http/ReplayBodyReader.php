<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

/** Reads a bounded request body without consuming the application's stream. */
final class ReplayBodyReader
{
    private const int MAX_BODY_BYTES = 65_536;

    public static function read(ServerRequestInterface $request): ?string
    {
        $stream = $request->getBody();
        $size = $stream->getSize();
        if ($size === 0) {
            return '';
        }

        if (!$stream->isSeekable() || $size === null || $size > self::MAX_BODY_BYTES) {
            return null;
        }

        $position = null;
        try {
            $position = $stream->tell();
            $stream->rewind();
            $body = $stream->getContents();
        } catch (Throwable) {
            if (is_int($position)) {
                self::restore($stream, $position);
            }

            return null;
        }

        if (!self::restore($stream, $position)) {
            return null;
        }

        return $body;
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
