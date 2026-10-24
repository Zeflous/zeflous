<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The request's own media type: the bare type segment of its
 * `Content-Type` line, parameters stripped and trimmed -- the request
 * factory's own ingress spelling ({@see RequestFactory::formBody()}),
 * lifted to the seam the body-parser family's negotiation table reads.
 *
 * A `Content-Type` line with parameters (`application/json;
 * charset=utf-8`) is the type the grammar walks, and a line with no
 * type segment answers the empty string -- the table's own gate reads
 * that emptiness as "no parser owns this request".
 */
final class RequestMediaType
{
    /**
     * The bare type segment of the request's `Content-Type` line.
     */
    public static function segment(ServerRequestInterface $serverRequest): string
    {
        return mb_trim(explode(';', $serverRequest->getHeaderLine('Content-Type'))[0]);
    }
}
