<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The double-failure answer: a plain-text 500 for when the error response
 * machinery itself failed, so the client still receives the status and the
 * correlation id.
 *
 * Attribution: legacy `zef-framework` `Middleware/GlobalErrorHandler`
 * (`internalErrorResponse()`'s catch arm, move-verbatim behaviour).
 */
final class FallbackResponse
{
    /**
     * Builds the plain-text internal-error response carrying the
     * correlation id.
     */
    public static function internalError(string $correlationId): Response
    {
        return new Response(
            500,
            [
                'Content-Type' => 'text/plain',
                'X-Request-ID' => $correlationId,
            ],
            'Internal Server Error',
        );
    }
}
