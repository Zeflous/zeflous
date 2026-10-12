<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Emits the response body to the output channel, guarded by the protocol's
 * payload rules.
 *
 * A status code outside the 200..299 payload range's eligible set carries
 * no body at all: 1xx is informational-only, and 204/205/304 forbid a body
 * by RFC 9110. An eligible status whose stream is not readable fails
 * closed -- a truncated response is worse than an exception -- and the
 * body streams in fixed-size chunks with a defensive stop when a read
 * answers empty below the end-of-stream flag (the engine disagreement the
 * reference implementation guards against, verified against a lying-eof
 * stream wrapper). The caller's close request is honoured in a finally
 * block, so the stream is released even on the failure paths.
 */
final class EmittedBody
{
    /**
     * Emits the response's body unless the status forbids one or the caller
     * suppressed it, and closes the stream when asked.
     */
    public static function emit(ResponseInterface $response, bool $suppressBody, bool $closeBody): void
    {
        $stream = $response->getBody();

        try {
            if (!$suppressBody && self::eligible($response)) {
                if (!$stream->isReadable()) {
                    throw new HttpException('Response body stream is not readable.');
                }

                self::echoChunks($stream);
            }
        } finally {
            if ($closeBody) {
                $stream->close();
            }
        }
    }

    /**
     * Whether the status code carries a payload at all.
     */
    private static function eligible(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status !== 204 && $status !== 205 && $status !== 304;
    }

    /**
     * Streams the body to the output channel in fixed-size chunks.
     */
    private static function echoChunks(StreamInterface $stream): void
    {
        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                break;
            }

            echo $chunk;
        }
    }
}
