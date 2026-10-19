<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use LogicException;
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
            $updated = $response->withHeader('Allow', implode(', ', $throwable->allowedMethods));
            if (!$updated instanceof ResponseInterface) {
                throw new LogicException('PSR-7 response header updates must preserve the response type.');
            }
            $response = $updated;
        }

        if ($throwable instanceof RateLimitException) {
            $updated = $response->withHeader('Retry-After', (string) $throwable->retryAfterSeconds);
            if (!$updated instanceof ResponseInterface) {
                throw new LogicException('PSR-7 response header updates must preserve the response type.');
            }
            $response = $updated;
        }

        $updated = $response
            ->withHeader('Vary', 'Accept')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Request-ID', $requestId)
        ;
        if (!$updated instanceof ResponseInterface) {
            throw new LogicException('PSR-7 response header updates must preserve the response type.');
        }

        return $updated;
    }
}
