<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use GuzzleHttp\Exception\GuzzleException;
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
            $sqlState = self::sqlState($exception);
            $details = preg_match('/^[0-9A-Z]{5}$/iD', $sqlState) === 1
                ? ['sql_state' => mb_strtoupper($sqlState)]
                : [];

            return new InternalException(
                'A database operation failed.',
                0,
                $exception,
                'DATABASE_ERROR',
                $details,
            );
        }

        // The ::class constant keeps the optional client a lazy name lookup
        // at runtime (instanceof never autoloads, so an absent Guzzle simply
        // answers false) while the analysis stub makes the contract visible
        // to both analysers -- the ext-redis stub's exact role.
        if ($exception instanceof GuzzleException) {
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

    /** Answers the SQLSTATE the driver reported, or the coarse code when it did not. */
    private static function sqlState(PDOException $exception): string
    {
        return self::sqlStateOf(
            \is_array($exception->errorInfo) ? $exception->errorInfo[0] ?? null : null,
            $exception->getCode(),
        );
    }

    /**
     * @param mixed      $element the first error-info entry when the driver supplied one
     * @param int|string $code    the PDO error code fallback
     */
    private static function sqlStateOf(mixed $element, int|string $code): string
    {
        return \is_string($element) ? $element : (string) $code;
    }

    private static function shortClassName(Throwable $exception): string
    {
        $class = $exception::class;
        $separator = mb_strrpos($class, '\\');

        return $separator === false ? $class : mb_substr($class, $separator + 1);
    }
}
