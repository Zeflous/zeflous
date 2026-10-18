<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The stamped envelope a failure is answered with: the disclosure-approved
 * message under the mapped status, rendered through the JSON factory with
 * its plain-text fallback, and stamped with the request's correlation id.
 *
 * The answering pipeline has three steps and one fallback: the public
 * message is chosen by {@see ErrorDisclosure} (the typed family's
 * client-facing words against the untyped arms' dev-mode gating), the
 * status-and-message pair is rendered by {@see ErrorResponseFactory}
 * (whose `json_encode()` can itself throw over invalid UTF-8 -- the
 * double failure), the double failure falls back to
 * {@see FallbackResponse}'s plain-text 500 so the client still gets the
 * status and the correlation id, and every answer leaves stamped with
 * the correlation id the mapper resolved ({@see CorrelationId}).
 *
 * Extracted from the error mapping middleware (the error-handling lane,
 * ROADMAP.md "Error Handling System" phase 2): mapping a failure and
 * rendering its answer are separate responsibilities, and the RFC 9457
 * problem-details envelope of phase 1 replaces the rendering half here
 * without the mapper changing shape.
 */
final class ErrorEnvelope
{
    /**
     * Answers a mapped failure: the disclosure-approved message under the
     * status, rendered through the factory (or its plain-text fallback)
     * and stamped with the correlation id.
     *
     * @param int    $status        the client-facing status the failure maps to
     * @param bool   $debug         whether dev-mode diagnostics are enabled
     * @param string $correlationId the request's correlation id
     */
    public static function answered(
        Throwable $throwable,
        int $status,
        bool $debug,
        string $correlationId,
    ): ResponseInterface {
        $message = ErrorDisclosure::message($throwable, $status, $debug);

        return self::stamped(self::rendered($status, $message, $correlationId), $correlationId);
    }

    /**
     * Renders the error response, falling back to the plain-text 500 when
     * the envelope's construction itself fails (the double failure).
     */
    private static function rendered(int $status, string $message, string $correlationId): ResponseInterface
    {
        try {
            return ErrorResponseFactory::create($status, $message, $correlationId);
        } catch (Throwable) {
            return FallbackResponse::internalError($correlationId);
        }
    }

    /**
     * Stamps the correlation id onto a response.
     */
    private static function stamped(ResponseInterface $response, string $correlationId): ResponseInterface
    {
        $message = $response->withHeader('X-Request-ID', $correlationId);
        \assert($message instanceof ResponseInterface, 'withHeader() must preserve the response type.');

        return $message;
    }
}
