<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use DateTimeImmutable;

/**
 * The `If-Modified-Since` precondition (RFC 9110 §13.1.3): the stored
 * representation is not modified when its `Last-Modified` timestamp falls
 * at or before the client's -- whole-second comparison, because HTTP
 * dates carry no sub-second precision. A value no grammar shape of
 * {@see HttpDate} accepts is ignored (treated as "no precondition"), on
 * both the client and the server side, per the reference implementation
 * (legacy `zef-framework` `Adapters.Http.ETagMiddleware::notModifiedSince`).
 */
final class IfModifiedSince
{
    /**
     * Answers whether the representation last modified at
     * `$lastModified` is unmodified since the client's timestamp.
     */
    public static function notModified(string $ifModifiedSince, string $lastModified): bool
    {
        $client = HttpDate::parse($ifModifiedSince);
        $server = HttpDate::parse($lastModified);
        if (!$client instanceof DateTimeImmutable || !$server instanceof DateTimeImmutable) {
            return false;
        }

        return $server->getTimestamp() <= $client->getTimestamp();
    }
}
