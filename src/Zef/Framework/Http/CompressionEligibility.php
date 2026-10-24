<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The compression family's eligibility fold: the one question whether a
 * response earns the engine at all -- the status, the encoding it may
 * already carry, and the body's size against the family's threshold.
 *
 * The refusal order is the middleware walk's own cheapest-first: a
 * body-less status (the informational 1xx, 204/205/304) has nothing to
 * compress; a response that already carries a `Content-Encoding` -- an
 * application that encoded its own stream, or this family composed
 * twice -- is left alone, the double-compression refusal; and a body
 * below the threshold spends more CPU than the bytes save. A body
 * whose size the stream cannot report passes the gate: buffering the
 * whole body to learn its size is the memory posture this family
 * exists to avoid, and an unknown-size body is a streaming response
 * the operator chose -- the decision the family's KDD note records.
 */
final class CompressionEligibility
{
    /**
     * Whether the response earns the compression engine.
     *
     * @param ResponseInterface $response     the handler's answer
     * @param int               $minimumBytes the family's threshold
     */
    public static function eligible(ResponseInterface $response, int $minimumBytes): bool
    {
        $status = $response->getStatusCode();

        if ($status < 200 || $status === 204 || $status === 205 || $status === 304) {
            return false;
        }

        if ($response->getHeaderLine('Content-Encoding') !== '') {
            return false;
        }

        $size = $response->getBody()->getSize();

        return $size === null || $size >= $minimumBytes;
    }
}
