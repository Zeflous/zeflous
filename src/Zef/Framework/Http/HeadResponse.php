<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The response-side half of the HEAD method's identity rule (RFC 9110
 * section 9.3.2): the answer carries the GET representation's header
 * fields but never its body.
 *
 * The head view empties the body and drops the stale `Content-Length`:
 * the header describes a payload that will never arrive, and the
 * response object is left internally consistent -- the SAPI emitter
 * reconciles the lying framing header away anyway ({@see ContentLength}),
 * but a verbatim forwarder (the RoadRunner path in the reference)
 * would ship it and desynchronise keep-alive framing (the reference's
 * ZEF-DEEP-05 note). Every other header travels verbatim: validators
 * (`ETag`, `Last-Modified`), representation metadata (`Content-Type`)
 * and application fields alike, which is the RFC's "same header fields
 * as GET" property -- a `Content-Length` the RFC itself only permits,
 * never requires, for HEAD (section 8.6).
 *
 * Ported from the reference implementation's `headView()` (legacy
 * `zef-framework` `Adapters.Http.HttpRequestRunner`) with the same
 * object semantics, making it the empty-body 304's twin ({@see
 * NotModifiedResponse}): the `withBody()`-then-`withoutHeader()` chain
 * pinned to the response type at the seam.
 */
final class HeadResponse
{
    /**
     * Builds the head view of the answer a handler produced for the
     * GET logic of a HEAD request.
     */
    public static function view(ResponseInterface $response): ResponseInterface
    {
        $message = $response
            ->withBody(Stream::fromString(''))
            ->withoutHeader('Content-Length')
        ;
        \assert($message instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        return $message;
    }
}
