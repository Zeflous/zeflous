<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Redacts sensitive fields from opt-in local replay snapshots. */
final class ReplayPayloadSanitizer
{
    /**
     * @param mixed $value the decoded payload value
     *
     * @return null|array<string, mixed>|bool|float|int|string the sanitized value
     */
    public static function sanitize(mixed $value, int $depth = 0): array|bool|float|int|string|null
    {
        if ($depth >= 6) {
            return '[TRUNCATED]';
        }

        if (\is_array($value)) {
            return self::entries($value, $depth);
        }

        if (\is_string($value)) {
            $redacted = RedactedMessage::of(mb_substr($value, 0, 4096));
            $redacted = preg_replace(
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '[REDACTED_EMAIL]',
                $redacted,
            );

            return \is_string($redacted) ? $redacted : '[REDACTED]';
        }

        if (\is_int($value) || \is_float($value) || \is_bool($value) || $value === null) {
            return $value;
        }

        return '[UNSUPPORTED]';
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function entries(array $value, int $depth): array
    {
        $safe = [];
        $entries = \array_slice($value, 0, 100, true);

        foreach (array_keys($entries) as $key) {
            $name = \is_string($key) ? $key : (string) $key;
            if (preg_match('/password|passwd|secret|token|authorization|cookie|csrf|api[_-]?key/i', $name) === 1) {
                $safe[$name] = '[REDACTED]';

                continue;
            }

            $safe[$name] = self::sanitize($entries[$key], $depth + 1);
        }

        return $safe;
    }
}
