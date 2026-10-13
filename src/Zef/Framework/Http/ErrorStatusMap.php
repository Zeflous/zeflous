<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The error status an HTTP-layer failure maps to: the ingress gate's body
 * budget refusal answers 413, every other layer failure answers 400.
 *
 * The layer folded the reference implementation's three ingress signals
 * (`PayloadTooLargeException`, `StreamNotWritableException`,
 * `InvalidArgumentException`) into its single {@see HttpException} root with
 * byte-identical message texts (the H2 decision), so the message is the only
 * channel the semantics survived on -- the refusal text is pinned to the
 * real throw sites by the end-to-end test that runs the actual ingress
 * guard under an oversized claim, not by sharing a constant with it. The
 * 400 default is the coarse shim posture: a `HttpException` surfacing
 * through a handler means the HTTP message itself was rejected at the
 * boundary, which is a malformed request until the router lane's
 * `MethodNotAllowed` signal and the error-handling lane's typed hierarchy
 * (401/403/404/409/429) refine the map.
 *
 * Attribution: legacy `zef-framework` `Adapters/Kernel/HttpRequestRunner`
 * (the `PayloadTooLargeException` -> 413 / `InvalidArgumentException` -> 400
 * ingress mapping, folded onto the layer's exception root).
 */
final class ErrorStatusMap
{
    /** The body budget's refusal message (byte-identical to the throw sites). */
    private const string BODY_TOO_LARGE = 'Request body exceeds configured size limit.';

    private const int CONTENT_TOO_LARGE = 413;

    private const int BAD_REQUEST = 400;

    /**
     * Answers the client-facing status for an HTTP-layer failure.
     */
    public static function statusFor(HttpException $httpException): int
    {
        return $httpException->getMessage() === self::BODY_TOO_LARGE
            ? self::CONTENT_TOO_LARGE
            : self::BAD_REQUEST;
    }
}
