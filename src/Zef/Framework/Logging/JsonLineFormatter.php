<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use InvalidArgumentException;
use Stringable;

/** Formats one PSR-3 record into the JSONL line the writer appends. */
final class JsonLineFormatter
{
    private const array LEVELS = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    ];

    /**
     * @param array<string, mixed> $context
     */
    public static function line(mixed $level, string|Stringable $message, array $context): string
    {
        if (!\in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Unknown PSR-3 log level.');
        }

        return json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'message' => LogContextSanitizer::redact((string) $message),
            'context' => LogContextSanitizer::sanitize($context),
        ], \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    }
}
