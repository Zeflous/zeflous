<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use Throwable;

/** Sanitizes structured log fields before they cross the logging boundary. */
final class LogContextSanitizer
{
    /**
     * The context fold's entry budget: the tail beyond it drops, and the
     * truncation marker names the drop (a silent shrink is a lie the
     * record never owed).
     */
    private const int ENTRY_BUDGET = 100;

    /**
     * The throwable fold's chain budget: wrap links beyond it stop at
     * the marker, never at the stack.
     */
    private const int CHAIN_BUDGET = 10;

    /**
     * Redacts credential-bearing shapes from a free-form string: any
     * authorization scheme an `=`/`:` separator introduces (prose never
     * carries the separator, so a sentence keeps its diagnostics), the
     * two schemes a bare whitespace may introduce, the bare scheme word
     * a credential-shaped value follows (`Bearer eyJ...` with no
     * authorization word at all), the vocabulary pairs (`password=`,
     * `token=` ...) whose values are secrets themselves, and the
     * session-id grammar a URL query rides (`PHPSESSID=...`).
     */
    public static function redact(string $value): string
    {
        $authorizationRedacted = preg_replace(
            '/\b(authorization)\b(\s*[=:]\s*)[!#$%&\'*+.^_`|~0-9A-Za-z-]+\s+[^\r\n]*/i',
            '$1$2[REDACTED]',
            $value,
        );
        $authorizationRedacted = preg_replace(
            '/\b(authorization)\b(\s+)(?:Bearer|Basic)\s+[^\r\n]*/i',
            '$1$2[REDACTED]',
            \is_string($authorizationRedacted) ? $authorizationRedacted : $value,
        );
        $bareSchemeRedacted = preg_replace(
            '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i',
            '$1 [REDACTED]',
            \is_string($authorizationRedacted) ? $authorizationRedacted : $value,
        );
        $redacted = preg_replace(
            '/\b(password|passwd|token|secret|api[_-]?key|phpsessid|session[_-]?id|sid)\b(\s*[=:]\s*|\s+)(["\']?)[^\s"\']{4,}/i',
            '$1$2[REDACTED]',
            \is_string($bareSchemeRedacted) ? $bareSchemeRedacted : $value,
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
        $entries = \array_slice($context, 0, self::ENTRY_BUDGET, true);

        foreach (array_keys($entries) as $key) {
            $name = \is_string($key) ? $key : (string) $key;
            if (preg_match('/password|passwd|secret|token|authorization|cookie|csrf|api[_-]?key|session|phpsessid|sid/i', $name) === 1) {
                $safe[$name] = '[REDACTED]';

                continue;
            }

            $safe[$name] = self::value($entries[$key], $depth);
        }

        if (\count($context) > self::ENTRY_BUDGET) {
            $safe['_truncated'] = true;
        }

        return $safe;
    }

    /**
     * @return null|array<string, mixed>|bool|float|int|string the sanitized value
     */
    private static function value(mixed $value, int $depth): array|bool|float|int|string|null
    {
        return match (true) {
            $value instanceof Throwable => self::throwable($value),
            \is_string($value) => self::redact(mb_substr($value, 0, 4096)),
            \is_float($value) => NonFiniteFloat::spelling($value),
            \is_int($value), \is_bool($value), $value === null => $value,
            \is_array($value) => self::sanitize($value, $depth + 1),
            default => '[' . get_debug_type($value) . ']',
        };
    }

    /**
     * The throwable fold: the exception's own three facts plus the
     * wrapped chain it carries -- the previous link is the root cause a
     * wrapped diagnostic exists to name, and a record that drops it
     * answers a causeless failure. The chain folds under its own bound:
     * a pathological wrap chain stops at the marker, never at the stack.
     *
     * @return array<string, mixed>
     */
    private static function throwable(Throwable $throwable): array
    {
        $fold = [
            'class' => $throwable::class,
            'message' => self::redact($throwable->getMessage()),
            'code' => $throwable->getCode(),
        ];

        $previous = $throwable->getPrevious();

        if ($previous instanceof Throwable) {
            $fold['previous'] = self::throwableLink($previous, 1);
        }

        return $fold;
    }

    /**
     * One wrapped link of the chain, nested under its parent the way
     * the wrap itself nests -- each link the same three facts, the
     * budget's marker the honest end of a pathological chain.
     *
     * @return array<string, mixed>
     */
    private static function throwableLink(Throwable $throwable, int $depth): array
    {
        $link = [
            'class' => $throwable::class,
            'message' => self::redact($throwable->getMessage()),
            'code' => $throwable->getCode(),
        ];

        $previous = $throwable->getPrevious();

        if ($previous instanceof Throwable) {
            if ($depth >= self::CHAIN_BUDGET) {
                $link['_truncated'] = true;

                return $link;
            }

            $link['previous'] = self::throwableLink($previous, $depth + 1);
        }

        return $link;
    }
}
