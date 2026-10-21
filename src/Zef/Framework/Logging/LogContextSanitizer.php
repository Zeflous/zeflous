<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use Throwable;

/** Sanitizes structured log fields before they cross the logging boundary. */
final class LogContextSanitizer
{
    public static function redact(string $value): string
    {
        $authorizationRedacted = preg_replace(
            '/\b(authorization)\b(\s*[=:]\s*|\s+)(?:Bearer|Basic)\s+[^\r\n]*/i',
            '$1$2[REDACTED]',
            $value,
        );
        $redacted = preg_replace(
            '/\b(password|passwd|token|secret|api[_-]?key)\b(\s*[=:]\s*|\s+)(["\']?)[^\s"\']{4,}/i',
            '$1$2[REDACTED]',
            \is_string($authorizationRedacted) ? $authorizationRedacted : $value,
        );

        return \is_string($redacted) ? $redacted : '[REDACTED]';
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $context, int $depth = 0): array
    {
        if ($depth >= 5) {
            return ['_truncated' => true];
        }

        $safe = [];
        $entries = \array_slice($context, 0, 100, true);

        foreach (array_keys($entries) as $key) {
            $name = \is_string($key) ? $key : (string) $key;
            if (preg_match('/password|passwd|secret|token|authorization|cookie|csrf|api[_-]?key/i', $name) === 1) {
                $safe[$name] = '[REDACTED]';

                continue;
            }

            $safe[$name] = self::value($entries[$key], $depth);
        }

        return $safe;
    }

    /**
     * @return null|array<string, mixed>|bool|float|int|string the sanitized value
     */
    private static function value(mixed $value, int $depth): array|bool|float|int|string|null
    {
        return match (true) {
            $value instanceof Throwable => [
                'class' => $value::class,
                'message' => self::redact($value->getMessage()),
                'code' => $value->getCode(),
            ],
            \is_string($value) => self::redact(mb_substr($value, 0, 4096)),
            \is_float($value) => is_finite($value) ? $value : (string) $value,
            \is_int($value), \is_bool($value), $value === null => $value,
            \is_array($value) => self::sanitize($value, $depth + 1),
            default => '[' . get_debug_type($value) . ']',
        };
    }
}
