<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Conditional GET middleware (RFC 9110 §13) -- the HTTP layer's first
 * PSR-15 middleware.
 *
 * For successful GET/HEAD responses it evaluates the request's
 * preconditions against the response's declared validator -- the
 * entity-tag of the selected representation, RFC 9110 §13.1.2 -- when
 * the handler set one, otherwise against the strong SHA-256 ETag
 * computed from a non-empty body:
 *
 * - `If-None-Match` (ETag comparison, `*` and weak `W/` forms supported)
 * - `If-Modified-Since` (any response carrying `Last-Modified`, body-less
 *   or not, and never when the request also carries `If-None-Match`, per
 *   RFC 9110 §13.1.3)
 *
 * When a precondition matches, a body-less 304 with the validator headers
 * is returned so caches can reuse their copy ({@see NotModifiedResponse});
 * otherwise the strong validator is stamped onto the 200 unless the
 * handler already set one. The response body is consumed to hash it --
 * a seekable stream's cursor is restored afterwards (the audit #303
 * regression: the shared stream flows on to the emitter, which must
 * still ship the full representation on the cache-miss path).
 *
 * OPT-IN: add the middleware to the stack of an application that wants
 * conditional requests; it is intentionally not part of any default
 * stack. Ported from the reference implementation (legacy
 * `zef-framework` `Adapters.Http.ETagMiddleware`); the precondition
 * precedence chain lives in {@see RequestPreconditions}, the validator
 * in {@see StrongEtag} and the cursor-restoring body read in
 * {@see RepresentationBody}.
 *
 * Deviation from the reference, restructured for observability: the
 * reference's early return for "empty body and no `Last-Modified`"
 * preceded a fall-through path whose observable behaviour was identical
 * (no validator to compute, no precondition able to match, nothing to
 * stamp) -- a guard no test could distinguish from its own removal. The
 * guard is folded away and the same conditions gate the fall-through
 * instead, where each has a kill channel.
 */
final class ETagMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $this->applyConditionalGet($request, $handler->handle($request));
    }

    /**
     * Applies the conditional-GET evaluation to an eligible response.
     */
    private function applyConditionalGet(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
    ): ResponseInterface {
        $method = mb_strtoupper($serverRequest->getMethod());
        if (($method !== 'GET' && $method !== 'HEAD') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $stream = $response->getBody();
        $body = RepresentationBody::read($stream);

        return $this->resolvePreconditions($serverRequest, $response, $body);
    }

    private function resolvePreconditions(
        ServerRequestInterface $serverRequest,
        ResponseInterface $response,
        string $body,
    ): ResponseInterface {
        $etag = StrongEtag::of($body);
        $declared = $response->getHeaderLine('ETag');
        $validator = $declared !== '' ? $declared : $etag;
        $lastModified = $response->getHeaderLine('Last-Modified');

        if (RequestPreconditions::matches($serverRequest, $validator, $lastModified)) {
            return NotModifiedResponse::from($response, $validator);
        }

        if ($etag === '' || $response->hasHeader('ETag')) {
            return $response;
        }

        $message = $response->withHeader('ETag', $etag);
        \assert($message instanceof ResponseInterface, 'withHeader() must preserve the response type.');

        return $message;
    }
}
