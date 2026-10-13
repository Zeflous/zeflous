<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The read-ahead probe of the ingress gate: the delivered bytes judged
 * before dispatch, even when the claim and the stated size are absent,
 * lying or understated.
 *
 * The probe exists because a lazy wrapper alone can be bypassed by a
 * handler that ignores the body or casts a stream to a string: it reads
 * at most one byte past the budget through the layer's
 * {@see LimitedInputStream}, so a body that crosses the policy fails
 * closed at the gate itself. The probe is non-destructive by design: a
 * seekable body is rewound, probed, and restored to its original cursor
 * in a `finally` -- the request that passes the gate keeps its stream
 * and its own instance exactly where it was. A non-seekable body cannot
 * be restored, so the probed contents become the request's replacement
 * body -- a seekable in-memory stream -- and nothing readable is lost.
 *
 * Attribution: legacy `zef-framework` `Adapters/Http/RequestFactory`
 * (`validateIngress()`'s read-ahead half, move-verbatim behaviour).
 */
final class IngressProbe
{
    /**
     * Verifies the request's delivered bytes under the policy.
     *
     * @param ServerRequestInterface $serverRequest     the request entering the
     *                                                  application ingress
     * @param RequestBodyPolicy      $requestBodyPolicy the ingress policy the
     *                                                  body serves
     *
     * @return ServerRequestInterface the request to dispatch -- the same
     *                                request, its body and cursor untouched, when the
     *                                body could be restored; a request whose body is
     *                                the probed contents when it could not
     *
     * @throws HttpException when the delivered bytes exceed the policy
     */
    public static function verify(
        ServerRequestInterface $serverRequest,
        RequestBodyPolicy $requestBodyPolicy,
    ): ServerRequestInterface {
        $stream = $serverRequest->getBody();
        $position = $stream->isSeekable() ? $stream->tell() : null;

        try {
            if ($position !== null) {
                $stream->rewind();
            }

            $contents = new LimitedInputStream($stream, $requestBodyPolicy)->getContents();
        } finally {
            if ($position !== null) {
                $stream->seek($position);
            }
        }

        if ($position === null) {
            $guarded = $serverRequest->withBody(Stream::fromString($contents));

            // The PSR-7 contract preserves the request's own type through
            // withBody(); the assertion states that narrowing for the static
            // analysers and fails loudly in development against a
            // contract-violating foreign implementation.
            \assert($guarded instanceof ServerRequestInterface, 'withBody() must preserve the request type');

            return $guarded;
        }

        return $serverRequest;
    }
}
