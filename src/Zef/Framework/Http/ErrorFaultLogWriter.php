<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Log\LoggerInterface;
use Throwable;

/** Writes one error record and contains logger failures at the final fallback. */
final class ErrorFaultLogWriter
{
    /** @param array<string, mixed> $context */
    public static function write(?LoggerInterface $logger, string $level, array $context): void
    {
        try {
            if ($logger !== null) {
                $logger->log($level, 'Request failed', $context);

                return;
            }

            unset($context['exception']);
            error_log((string) json_encode([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'message' => 'Request failed',
                'context' => $context,
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $throwable) {
            error_log('ZEF logging failure: ' . $throwable::class);
        }
    }
}
