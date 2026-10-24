<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The compression family's PSR-15 boundary: the response body swapped
 * for its compressed form when the client accepts a coding this
 * runtime can produce and the body earns it -- the roadmap's Phase
 * 3.2 output-compression engine, over the incremental zlib seam.
 *
 * The order of the walk is cheapest-refusal-first, and every refusal
 * answers the handler's own response untouched: a disabled family
 * never reads the request's encoding line at all (the timing
 * family's inert posture); the eligibility fold
 * ({@see CompressionEligibility}) owns the response's own refusals --
 * the body-less status, the `Content-Encoding` a second compression
 * must never touch, the body below the family's threshold -- and a
 * client that accepts no producible coding gets identity. Only then
 * does the engine run.
 *
 * The compressed answer carries the `Content-Encoding` of the coding
 * it used and varies on `Accept-Encoding` ({@see CompressionVary}):
 * a cache that stores the compressed representation under the bare
 * URL would serve gzip bytes to a client that asked for identity,
 * and the `Vary` line is what prevents it. The stale
 * `Content-Length` of the uncompressed form is dropped at the
 * response level -- the emitter walks the body in chunks and
 * reconciles framing itself, and a response object whose declared
 * length matches its actual body is consistent for every downstream
 * consumer, the head-view doctrine replayed.
 *
 * A body whose size the stream cannot report is compressed without
 * the threshold's protection: the alternative -- buffering the whole
 * body to learn its size -- is the memory posture this family exists
 * to avoid, and an unknown-size body is a streaming response the
 * operator chose. The decision, with the identity-not-406 one, is
 * recorded in the family's KDD note.
 */
final readonly class CompressionMiddleware implements MiddlewareInterface
{
    private CompressionNegotiator $compressionNegotiator;

    /**
     * @param CompressionPolicy          $compressionPolicy     the family's frozen
     *                                                          knob set
     * @param null|CompressionNegotiator $compressionNegotiator the coding negotiator;
     *                                                          defaults to one over
     *                                                          the engine's own
     *                                                          capability probe
     */
    public function __construct(
        private CompressionPolicy $compressionPolicy,
        ?CompressionNegotiator $compressionNegotiator = null,
    ) {
        $this->compressionNegotiator = $compressionNegotiator ?? new CompressionNegotiator();
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->compressionPolicy->enabled) {
            return $handler->handle($request);
        }

        $response = $handler->handle($request);

        $coding = $this->compressionNegotiator->negotiate(
            $request->getHeaderLine('Accept-Encoding'),
        );

        if ($coding === null || $coding === '') {
            return $response;
        }

        return $this->compressed($response, $coding);
    }

    /**
     * The compressed answer for one eligible response, or the response
     * itself when it refuses the engine.
     */
    private function compressed(ResponseInterface $response, string $coding): ResponseInterface
    {
        if (!CompressionEligibility::eligible($response, $this->compressionPolicy->minimumBytes)) {
            return $response;
        }

        return CompressedResponse::answer(
            $response,
            $coding,
            $this->compressionPolicy->level,
        );
    }
}
