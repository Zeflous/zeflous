<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use PDOException;
use Throwable;
use Zef\Framework\Exception\InternalException;

/**
 * Normalizes known infrastructure failures without importing optional clients.
 *
 * This adapter lives at the HTTP boundary: domain/application code remains
 * independent of PDO and Guzzle, and the framework keeps zero runtime deps.
 */
final class ThirdPartyExceptionMapper
{
    public static function normalize(Throwable $exception): Throwable
    {
        if ($exception instanceof PDOException) {
            $sqlState = (string) $exception->getCode();
            $details = preg_match('/^[0-9A-Z]{5}$/iD', $sqlState) === 1
                ? ['sql_state' => strtoupper($sqlState)]
                : [];

            return new InternalException(
                'A database operation failed.',
                0,
                $exception,
                'DATABASE_ERROR',
                $details,
            );
        }

        // A string target avoids a hard dependency on Guzzle being installed.
        if (is_a($exception, 'GuzzleHttp\\Exception\\GuzzleException')) {
            return new InternalException(
                'An upstream service request failed.',
                0,
                $exception,
                'UPSTREAM_REQUEST_FAILED',
                ['exception_class' => self::shortClassName($exception)],
            );
        }

        return $exception;
    }

    private static function shortClassName(Throwable $exception): string
    {
        $class = $exception::class;
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }
}
