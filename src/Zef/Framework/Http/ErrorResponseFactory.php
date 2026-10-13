<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use JsonException;

/**
 * The JSON error envelope: one response shape for every mapped failure.
 *
 * The reference implementation answered ingress failures and pipeline
 * failures with two different shapes (the runner's `{"error": "Bad
 * Request", "status": 400}` against the handler guard's
 * `{"error": true, ...}`); this slice funnels every failure through one
 * middleware, so the shapes fold into the handler guard's envelope with the
 * correlation id always present -- the client can correlate a 413 exactly
 * like a 500. `json_encode()` keeps the reference's flag set
 * (`JSON_THROW_ON_ERROR` alone: forward slashes stay escaped), and the
 * throw is the honest failure channel -- a message that is not valid UTF-8
 * makes the encoding fail, which is the double-failure the middleware's
 * plain-text fallback exists for.
 *
 * Attribution: legacy `zef-framework` `Middleware/ErrorResponseFactory`
 * (`create()`, move-verbatim envelope; the dev-mode message gating moved to
 * {@see ErrorDisclosure} so the factory is a pure renderer).
 */
final class ErrorResponseFactory
{
    /**
     * Builds the JSON error response for a status, its public message and
     * the request's correlation id.
     *
     * @param int    $status        the client-facing status code
     * @param string $message       the disclosure-approved public message
     * @param string $correlationId the request's correlation id
     *
     * @throws JsonException when the message is not valid UTF-8
     */
    public static function create(int $status, string $message, string $correlationId): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode(
                [
                    'error' => true,
                    'status' => $status,
                    'message' => $message,
                    'correlation_id' => $correlationId,
                ],
                \JSON_THROW_ON_ERROR,
            ),
        );
    }
}
