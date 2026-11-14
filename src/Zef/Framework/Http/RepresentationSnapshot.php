<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The representation-survival answer of the conditional read: the response
 * whose body a reading middleware has consumed, made whole again.
 *
 * Audit #303's doctrine for the seekable half -- the shared stream flows on
 * to the emitter, which must still ship the full representation on the
 * cache-miss path -- restores the cursor after the read. A non-seekable
 * stream has no cursor to restore: once {@see RepresentationBody::read()}
 * has consumed it, the response it leaves behind is a success-headered
 * empty shell, and every later body reader -- the compression family's
 * {@see CompressedBody} walking `eof()`, the emitter's own chunked walk --
 * answers the nothing that is left (issue #771: an ETag-armed,
 * compression-armed pipeline served a 200 whose wire body was the gzip of
 * an empty string while its `ETag` claimed the hash of the full 4096 bytes
 * -- a corruption every downstream cache would store, precisely because
 * every header said success).
 *
 * The honest restoration for the half that cannot rewind is replacement:
 * the response's body is swapped for a seekable `php://temp` snapshot
 * carrying exactly the bytes the read consumed, cursor at the start. The
 * snapshot is what the stream itself can never again be -- rewound -- and
 * every downstream consumer (the compression engine, the emitter, a later
 * body-reading middleware) reads the representation whole. The swap rides
 * `php://temp`'s memory allowance the same way the compression family's
 * destination does: the payload lives in memory up to its allowance and
 * spills to disk beyond it, so snapshotting a large streamed body never
 * becomes a large allocation.
 *
 * A seekable stream never reaches the swap: its restoration is the cursor
 * rewind {@see RepresentationBody::read()} already performed, and the
 * response keeps its own stream instance.
 */
final class RepresentationSnapshot
{
    /**
     * Answers the response whose representation survives the reading read.
     *
     * @param ResponseInterface $response the response whose body was read
     * @param StreamInterface   $stream   the body stream the read consumed
     * @param string            $content  the bytes the read consumed
     */
    public static function answer(
        ResponseInterface $response,
        StreamInterface $stream,
        string $content,
    ): ResponseInterface {
        if ($stream->isSeekable()) {
            return $response;
        }

        $message = $response->withBody(Stream::fromString($content));
        \assert($message instanceof ResponseInterface, 'withBody() must preserve the response type.');

        return $message;
    }
}
