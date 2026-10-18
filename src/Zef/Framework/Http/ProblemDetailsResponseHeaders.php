<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zef\Framework\Exception\MethodNotAllowedException;
use Zef\Framework\Exception\RateLimitException;

/** Applies HTTP semantics and cache/security headers shared by error formats. */
final class ProblemDetailsResponseHeaders
{
    public static function apply(ResponseInterface $response, Throwable $throwable, string $requestId): ResponseInterface
    {
        if ($throwable instanceof MethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $throwable->allowedMethods));
        }

        if ($throwable instanceof RateLimitException) {
            $response = $response->withHeader('Retry-After', (string) $throwable->retryAfterSeconds);
        }

        return $response
            ->withHeader('Vary', 'Accept')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Request-ID', $requestId);
    }
}
