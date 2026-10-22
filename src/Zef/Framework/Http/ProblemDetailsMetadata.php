<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Stable RFC 9457 type and title metadata for framework-owned HTTP failures. */
final class ProblemDetailsMetadata
{
    public static function typeFor(int $status): string
    {
        $slug = match ($status) {
            400 => 'validation-error',
            401 => 'authentication-required',
            403 => 'forbidden',
            404 => 'not-found',
            405 => 'method-not-allowed',
            406 => 'not-acceptable',
            409 => 'conflict',
            413 => 'content-too-large',
            429 => 'rate-limit-exceeded',
            default => 'internal-error',
        };

        return 'https://zef.io/errors/' . $slug;
    }

    public static function titleFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Authentication Required',
            403 => 'Forbidden',
            404 => 'Resource Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            409 => 'Conflict',
            413 => 'Content Too Large',
            429 => 'Too Many Requests',
            default => 'Internal Server Error',
        };
    }
}
