<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\RateLimitException;
use Zef\Framework\Exception\ZefException;

/** Renders HTTP failures as RFC 9457 Problem Details or a safe HTML page. */
final class ProblemDetailsResponseFactory
{
    /**
     * @throws JsonException If the problem document cannot be encoded.
     */
    public static function create(
        Throwable $throwable,
        int $status,
        ServerRequestInterface $request,
        string $requestId,
        bool $debug,
    ): ResponseInterface {
        $detail = ErrorDisclosure::message($throwable, $status, $debug);
        $errorRequestContext = $request->getAttribute(ErrorRequestContext::ATTRIBUTE);
        $traceId = $throwable instanceof ZefException && $throwable->getTraceId() !== null
            ? $throwable->getTraceId()
            : ($errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->traceId() : $requestId);
        $instance = $request->getUri()->getPath();

        if (ErrorContentNegotiator::prefersHtml($request->getHeaderLine('Accept'))) {
            $response = HtmlErrorPageRenderer::render(
                $throwable,
                $status,
                $detail,
                $instance,
                $traceId,
                $debug,
                $errorRequestContext instanceof ErrorRequestContext
                    ? $errorRequestContext->durationMilliseconds()
                    : 0.0,
                $errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->queryCount() : 0,
                $errorRequestContext instanceof ErrorRequestContext ? $errorRequestContext->exceptionCount() : 1,
            );
        } else {
            $problem = [
                'type' => ProblemDetailsMetadata::typeFor($status),
                'title' => ProblemDetailsMetadata::titleFor($status),
                'status' => $status,
                'detail' => $detail,
                'instance' => $instance !== '' ? $instance : '/',
                'trace_id' => $traceId,
            ];

            if ($throwable instanceof ZefException) {
                $problem['code'] = $throwable->getErrorCode();

                if ($debug && $throwable->getDetails() !== []) {
                    $problem['details'] = ErrorDetailsSanitizer::sanitize($throwable->getDetails());
                }
            }

            $response = new Response(
                $status,
                ['Content-Type' => 'application/problem+json; charset=utf-8'],
                json_encode($problem, JSON_THROW_ON_ERROR),
            );
        }

        if ($throwable instanceof MethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $throwable->allowedMethods));
        }

        if ($throwable instanceof RateLimitException) {
            $response = $response->withHeader('Retry-After', (string) $throwable->retryAfterSeconds);
        }

        $response = $response->withHeader('Vary', 'Accept');
        $response = $response->withHeader('Cache-Control', 'no-store');
        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');

        return $response->withHeader('X-Request-ID', $requestId);
    }
}
