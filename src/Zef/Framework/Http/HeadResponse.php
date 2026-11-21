<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The response-side half of the HEAD method's identity rule (RFC 9110
 * section 9.3.2): the answer carries the GET representation's header
 * fields but never its body.
 *
 * The head view carries the GET twin's Content-Length (issue #801's
 * finding 1: the first spelling dropped the header as "stale" -- but
 * section 9.3.2 says a HEAD response SHOULD carry a Content-Length
 * equal to the GET's, the length clients that time HEAD utilisation
 * read) over the {@see HeadBody} view: the body answers the
 * representation's length and no content at all, so the emitter's
 * length reconciliation keeps the framing header the empty swap's
 * zero-size stream made it drop, and a verbatim forwarder (the
 * RoadRunner path in the reference) ships a header that tells the
 * truth about the GET twin rather than none at all. Every other
 * header travels verbatim: validators (`ETag`, `Last-Modified`),
 * representation metadata (`Content-Type`) and application fields
 * alike, which is the RFC's "same header fields as GET" property.
 */
final class HeadResponse
{
    /**
     * The HEAD view of the GET answer the handler produced: the body
     * becomes the {@see HeadBody} view -- the GET twin's length, no
     * content -- and the Content-Length header carries that same length
     * (issue #801's finding 1, RFC 9110 section 9.3.2: a HEAD response
     * SHOULD carry a Content-Length equal to its GET twin's). A body
     * whose length cannot be measured (no size, or a cursor the stream
     * cannot answer) keeps the honest strip instead: the view never
     * reports a length it did not measure, and the stale declaration the
     * handler carried is replaced either way -- the measured length when
     * the measurement exists, nothing at all when it does not. A status
     * the framing doctrine itself forbids the field on never earns one
     * here either: the 1xx and 204 classes (RFC 9110 section 8.6's own
     * MUST NOT) and the 304, already the body-less answer its own
     * conditional fold built ({@see NotModifiedResponse} emptied the
     * body and retired the field) -- the field-value a 304 MAY carry is
     * the would-be 200's own length (section 8.6), a body the fold
     * already suppressed, so the zero the emptied stream measures is
     * that length's own lie, and the view's stamp would bless it. The
     * 304 keeps the framing its fold left, and the head view's
     * empty-body discipline alone rides it.
     */
    public static function view(ResponseInterface $response): ResponseInterface
    {
        $status = $response->getStatusCode();
        $stream = $response->getBody();
        $length = $stream->getSize();

        $message = $response->withBody(new HeadBody($length ?? 0));

        if ($status < 200 || $status === 204 || $status === 304 || $length === null || !$stream->isSeekable()) {
            $view = $message->withoutHeader('Content-Length');
            \assert($view instanceof ResponseInterface, 'the with* chain must preserve the response type.');

            return $view;
        }

        $view = $message->withHeader('Content-Length', (string) $length);
        \assert($view instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        return $view;
    }
}
