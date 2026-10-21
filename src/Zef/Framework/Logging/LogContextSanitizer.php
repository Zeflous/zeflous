<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use Throwable;

/** Sanitizes structured log fields before they cross the logging boundary. */
final class LogContextSanitizer
{
    /**
     * The context fold's depth budget: levels beyond it stop at the
     * truncation marker, never mid-structure.
     */
    private const int DEPTH_BUDGET = 5;

    /**
     * The context fold's entry budget: the tail beyond it drops, and the
     * truncation marker names the drop (a silent shrink is a lie the
     * record never owed).
     */
    private const int ENTRY_BUDGET = 100;

    /**
     * The string value's byte budget: a diagnostic longer than it is
     * cut before the redaction reads it.
     */
    private const int MESSAGE_BOUND = 4096;

    /**
     * The truncation marker every budgeted fold answers: the honest
     * name of a drop, never a silent shrink.
     */
    private const string TRUNCATED = '_truncated';

    /**
     * The fail-closed answer a redaction lane can always give: the one
     * safe string when no honest one remains.
     */
    private const string REDACTED = '[REDACTED]';

    /**
     * The key vocabulary a credential-bearing field name carries: the
     * whole pair redacts, the value is the secret itself.
     */
    private const string SENSITIVE_NAME = '/password|passwd|secret|token|authorization|cookie|csrf|api[_-]?key'
        . '|session|phpsessid|sid/i';

    /**
     * Redacts credential-bearing shapes from a free-form string:
     * {@see LogCredentialRedaction} keeps the passes and their
     * cascade, this seam keeps the callers' one spelling.
     */
    public static function redact(string $value): string
    {
        return LogCredentialRedaction::redact($value);
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $context, int $depth = 0): array
    {
        if ($depth >= self::DEPTH_BUDGET) {
            return [self::TRUNCATED => true];
        }

        $safe = [];
        $entries = \array_slice($context, 0, self::ENTRY_BUDGET, true);

        foreach (array_keys($entries) as $key) {
            $name = (string) $key;
            if (preg_match(self::SENSITIVE_NAME, $name) === 1) {
                $safe[$name] = self::REDACTED;

                continue;
            }

            $safe[$name] = self::value($entries[$key], $depth);
        }

        if (\count($context) > self::ENTRY_BUDGET) {
            $safe[self::TRUNCATED] = true;
        }

        return $safe;
    }

    /**
     * @return null|array<string, mixed>|bool|float|int|string the sanitized value
     */
    private static function value(mixed $value, int $depth): array|bool|float|int|string|null
    {
        return match (true) {
            $value instanceof Throwable => LogThrowableFold::fold($value),
            \is_string($value) => self::redact(mb_substr($value, 0, self::MESSAGE_BOUND)),
            \is_float($value) => NonFiniteFloat::spelling($value),
            \is_scalar($value), $value === null => $value,
            \is_array($value) => self::sanitize($value, $depth + 1),
            default => '[' . get_debug_type($value) . ']',
        };
    }
}
