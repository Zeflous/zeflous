<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Reconciles a declared Content-Length header against the body stream the
 * response would actually ship.
 *
 * A lying Content-Length (declared different from the octets actually sent)
 * truncates clients and desynchronises keep-alive proxies (RFC 9110
 * section 8.6; the request-smuggling CL.CL family), so the emitter strips
 * the header when it cannot be reconciled. The reference implementation's
 * arithmetic, move-verbatim: the remaining length is measured from the
 * stream's cursor (a mid-body cursor owes only its remainder), a negative
 * remainder clamps to zero, and an unmeasurable stream (no size, or no
 * position because the stream is not seekable) can never be reconciled and
 * therefore loses the header.
 */
final class ContentLength
{
    /**
     * Returns the response with an irreconcilable Content-Length removed.
     *
     * The declared value survives only when it is a plain digit string
     * whose integer value equals the stream's remaining length; every
     * other declared shape (non-numeric, negative-looking, padded, or
     * simply wrong) is a lying framing header and is dropped. This is a
     * header-level operation: the returned message feeds the field
     * emission only, never the body path (the reference implementation
     * threads the reconciled message into its body step, but a
     * Content-Length strip changes nothing a body emission could observe).
     */
    public static function reconcile(MessageInterface $message, StreamInterface $stream): MessageInterface
    {
        $declared = $message->getHeaderLine('Content-Length');
        if ($declared === '') {
            return $message;
        }

        $size = $stream->getSize();
        $consumed = $stream->isSeekable() ? $stream->tell() : null;
        $remaining = $size !== null && $consumed !== null ? max(0, $size - $consumed) : $size;

        if (!ctype_digit($declared) || $remaining === null || (int) $declared !== $remaining) {
            return $message->withoutHeader('Content-Length');
        }

        return $message;
    }
}
