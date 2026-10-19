<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Redacts sensitive fields from opt-in local replay snapshots. */
final class ReplayPayloadSanitizer
{
    public static function sanitize(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 6) {
            return '[TRUNCATED]';
        }

        if (\is_array($value)) {
            $safe = [];
            foreach (\array_slice($value, 0, 100, true) as $key => $item) {
                $name = \is_string($key) ? $key : (string) $key;
                if (preg_match('/password|passwd|secret|token|authorization|cookie|csrf|api[_-]?key/i', $name) === 1) {
                    $safe[$name] = '[REDACTED]';

                    continue;
                }

                $safe[$name] = self::sanitize($item, $depth + 1);
            }

            return $safe;
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
}
