<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

/**
 * The credential-bearing shapes a free-form string sheds before it
 * crosses the logging boundary ({@see LogContextSanitizer} keeps the
 * context fold that calls it).
 *
 * Five shapes, five passes: any authorization scheme an `=`/`:`
 * separator introduces (prose never carries the separator, so a
 * sentence keeps its diagnostics), the two schemes a bare whitespace
 * may introduce, the bare scheme word a credential-shaped value
 * follows (`Bearer eyJ...` with no authorization word at all), the
 * vocabulary pairs (`password=`, `token=` ...) whose values are
 * secrets themselves, and the session-id grammar a URL query rides
 * (`PHPSESSID=...`).
 *
 * The passes cascade honestly: a PCRE failure (the null
 * {@see preg_replace()} answers on an engine limit) hands the next
 * pass the original value, and the last pass's failure answers the
 * one safe string -- fail-closed to `[REDACTED]`, never to the
 * credential.
 */
final class LogCredentialRedaction
{
    /**
     * The replacement a redacted pair answers: the pair's own key and
     * separator kept, the credential itself never crossing.
     */
    private const string PAIR_REDACTED = '$1$2[REDACTED]';

    /**
     * Redacts the credential-bearing shapes from a free-form string.
     */
    public static function redact(string $value): string
    {
        $authorizationHeader = self::carry(
            preg_replace(
                '/\b(authorization)\b(\s*[=:]\s*)[!#$%&\'*+.^_`|~0-9a-z-]+\s+[^\r\n]*/i',
                self::PAIR_REDACTED,
                $value,
            ),
            $value,
        );
        $authorizationProse = self::carry(
            preg_replace(
                '/\b(authorization)\b(\s+)(?:Bearer|Basic)\s+[^\r\n]*/i',
                self::PAIR_REDACTED,
                $authorizationHeader,
            ),
            $value,
        );
        $bearerShape = self::carry(
            preg_replace(
                '/\b(Bearer|Basic)\s+[a-z0-9._~+\/=-]{8,}/i',
                '$1 [REDACTED]',
                $authorizationProse,
            ),
            $value,
        );
        $redacted = preg_replace(
            '/\b(password|passwd|token|secret|api[_-]?key|phpsessid|session[_-]?id|sid)\b'
            . '(\s*[=:]\s*|\s+)(["\']?)[^\s"\']{4,}/i',
            self::PAIR_REDACTED,
            $bearerShape,
        );

        return \is_string($redacted) ? $redacted : '[REDACTED]';
    }

    /**
     * One pass's honest carry: the pass's answer when the engine
     * answered a string, the original value when it hit its limit --
     * a failed pass never widens what the next one reads.
     */
    private static function carry(mixed $stage, string $value): string
    {
        return \is_string($stage) ? $stage : $value;
    }
}
