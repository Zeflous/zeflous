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
            if (!ErrorDetailKeys::accepts($key)) {
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
        return match (true) {
            \is_string($value) => RedactedMessage::of(mb_substr($value, 0, 2048)),
            \is_scalar($value) || $value === null => $value,
            \is_array($value) => self::sanitize($value, $depth + 1),
            default => '[' . get_debug_type($value) . ']',
        };
    }
}
