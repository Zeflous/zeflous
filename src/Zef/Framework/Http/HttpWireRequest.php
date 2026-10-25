<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\RequestInterface;

/**
 * The wire-dump family's request half: a request's wire block over
 * its own request line -- `METHOD SP request-target SP HTTP-version`,
 * the origin-form target the request resolves (path and query, never
 * the full URI, because that is what the wire carries).
 *
 * @internal
 */
final class HttpWireRequest
{
    /**
     * The request's wire block: request line, header lines, empty
     * line, body.
     *
     * @param int $bodyBound the maximum body byte count rendered
     */
    public static function rendered(RequestInterface $request, int $bodyBound): string
    {
        return HttpWireBlock::rendered(self::requestLine($request), $request, $bodyBound);
    }

    /**
     * The request line: method, resolved request target, protocol
     * version, each separated by a single space.
     *
     * @return non-empty-string
     */
    private static function requestLine(RequestInterface $request): string
    {
        return $request->getMethod()
            . ' ' . $request->getRequestTarget()
            . ' HTTP/' . $request->getProtocolVersion();
    }
}
