<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The worker answer's body: the response's payload read whole into the
 * wire shape's resident string.
 *
 * A worker runtime carries the answer as one payload (the body the PSR-7
 * response a RoadRunner worker returns holds, the string Swoole's
 * `end()` writes), so the body is read whole -- the {@see EmittedBody}
 * doctrine replayed onto a string destination: a status outside the
 * eligible set carries no body at all (1xx is informational-only,
 * 204/205/304 forbid a body by RFC 9110), an eligible status whose
 * stream is not readable fails closed -- a truncated answer is worse
 * than an exception -- and the read pumps fixed-size chunks with the
 * defensive stop when a read answers empty below the end-of-stream flag
 * (the engine disagreement the lying-eof guard has always answered).
 * The stream's cursor is the emitter's to place: the fold reads from
 * wherever the emitter left it, exactly as the SAPI twin's body path
 * does.
 */
final class WorkerResponseBody
{
    private const int CHUNK_BYTES = 8192;

    /**
     * Reads the response's body into the wire shape's string, guarded
     * by the protocol's payload rules.
     *
     * @throws HttpException when an eligible status carries a stream
     *                       that is not readable
     */
    public static function read(ResponseInterface $response): string
    {
        if (!self::carriesPayload($response->getStatusCode())) {
            return '';
        }

        $stream = $response->getBody();
        if (!$stream->isReadable()) {
            throw new HttpException('Response body stream is not readable.');
        }

        return self::readChunks($stream);
    }

    /**
     * Whether the status code carries a payload at all -- the
     * eligibility the SAPI emission path ({@see EmittedBody}) owns on
     * its side of the seam, spelled once here: the same RFC 9110 rules
     * answer the same question on both outbound lanes.
     */
    private static function carriesPayload(int $status): bool
    {
        return $status >= 200 && $status !== 204 && $status !== 205 && $status !== 304;
    }

    /**
     * Pumps the stream's remaining bytes into the resident string.
     */
    private static function readChunks(StreamInterface $stream): string
    {
        $body = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(self::CHUNK_BYTES);
            if ($chunk === '') {
                break;
            }

            $body .= $chunk;
        }

        return $body;
    }
}
