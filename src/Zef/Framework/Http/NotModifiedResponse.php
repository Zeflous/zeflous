<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The body-less 304 a satisfied conditional GET answers with (RFC 9110
 * §15.4.5): the status becomes 304, the body is emptied, the stale
 * `Content-Length` -- it describes a payload that will never arrive --
 * is dropped, and the response's validator travels along so caches can
 * key their reuse.
 *
 * `Content-Type` is kept: it is harmless representation metadata real
 * servers widely send. Ported from the reference implementation (legacy
 * `zef-framework` `Adapters.Http.ETagMiddleware::notModified`); the
 * ZEF-DEEP-05 note there (a lying `Content-Length` can hang sloppy
 * clients behind verbatim-header forwarders) is why the header is
 * removed here rather than left to the SAPI emitter to reconcile.
 */
final class NotModifiedResponse
{
    /**
     * Builds the 304 for a response whose precondition matched. A
     * computed validator (`$etag` non-empty) is stamped onto the 304; an
     * empty one (the `Last-Modified`-only path) leaves the header map
     * as the handler produced it.
     */
    public static function from(ResponseInterface $response, string $etag): ResponseInterface
    {
        $emptied = $response
            ->withStatus(304)
            ->withBody(Stream::fromString(''))
            ->withoutHeader('Content-Length')
        ;
        \assert($emptied instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        if ($etag === '') {
            return $emptied;
        }

        $stamped = $emptied->withHeader('ETag', $etag);
        \assert($stamped instanceof ResponseInterface, 'withHeader() must preserve the response type.');

        return $stamped;
    }
}
