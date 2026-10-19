<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Bounds and redacts developer diagnostic details before a debug response. */
final class ErrorDetailsSanitizer
{
    /**
     * @param array<array-key, mixed> $details
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $details, int $depth = 0): array
    {
        if ($depth >= 4) {
            return ['_truncated' => true];
        }

        $safe = [];
        $entries = \array_slice($details, 0, 50, true);

        foreach (array_keys($entries) as $key) {
            if (!\is_string($key) || preg_match('/^[a-zA-Z0-9_.-]{1,80}$/D', $key) !== 1) {
                continue;
            }

            $safe[$key] = self::value($entries[$key], $depth);
        }

        return $safe;
    }

    /**
     * @return null|array<string, mixed>|bool|float|int|string the sanitized value
     */
    private static function value(mixed $value, int $depth): array|bool|float|int|string|null
    {
        if (\is_string($value)) {
            return RedactedMessage::of(mb_substr($value, 0, 2048));
        }

        if (\is_int($value) || \is_float($value) || \is_bool($value) || $value === null) {
            return $value;
        }

        if (\is_array($value)) {
            return self::sanitize($value, $depth + 1);
        }

        return '[' . get_debug_type($value) . ']';
    }
}
