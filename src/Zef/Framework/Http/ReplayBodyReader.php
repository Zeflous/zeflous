<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/** Reads a bounded request body without consuming the application's stream. */
final class ReplayBodyReader
{
    private const int MAX_BODY_BYTES = 65_536;

    public static function read(ServerRequestInterface $serverRequest): ?string
    {
        $stream = $serverRequest->getBody();
        $size = $stream->getSize();
        if ($size === 0) {
            return '';
        }

        if (!$stream->isSeekable() || $size === null || $size > self::MAX_BODY_BYTES) {
            return null;
        }

        return StreamSnapshot::capture($stream);
    }
}
