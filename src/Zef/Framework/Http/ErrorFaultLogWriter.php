<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Log\LoggerInterface;
use Throwable;

/** Writes one error record and contains logger failures at the final fallback. */
final class ErrorFaultLogWriter
{
    /**
     * @param array<string, mixed> $context the record's bounded context
     * @param non-empty-string     $message the record's message; the exception
     *                                      lane's own records keep the historical
     *                                      "Request failed" spelling, and the diagnostic
     *                                      lane names its own ("Native diagnostic",
     *                                      "Uncaught fatal") so a stored record's
     *                                      origin is readable without its context
     */
    public static function write(
        ?LoggerInterface $logger,
        string $level,
        array $context,
        string $message = 'Request failed',
    ): void {
        try {
            if ($logger instanceof LoggerInterface) {
                $logger->log($level, $message, $context);

                return;
            }

            error_log(json_encode([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ], \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $throwable) {
            error_log('ZEF logging failure: ' . $throwable::class);
        }
    }
}
