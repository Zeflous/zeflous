<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The wire-dump family's response half: a response's wire block over
 * its own status line -- `HTTP-version SP status-code [SP
 * reason-phrase]`, the phrase omitted entirely when the response
 * carries none (an unregistered code's honest line ends at the code,
 * the status-line serializer's own doctrine, never a dangling space).
 *
 * @internal
 */
final class HttpWireResponse
{
    /**
     * The response's wire block: status line, header lines, empty
     * line, body.
     *
     * @param int $bodyBound the maximum body byte count rendered
     */
    public static function rendered(ResponseInterface $response, int $bodyBound): string
    {
        return HttpWireBlock::rendered(self::statusLine($response), $response, $bodyBound);
    }

    /**
     * The status line: protocol version, status code, and the reason
     * phrase only when the response carries one.
     *
     * @return non-empty-string
     */
    private static function statusLine(ResponseInterface $response): string
    {
        $reasonPhrase = $response->getReasonPhrase();

        return 'HTTP/' . $response->getProtocolVersion()
            . ' ' . $response->getStatusCode()
            . ($reasonPhrase === '' ? '' : ' ' . $reasonPhrase);
    }
}
