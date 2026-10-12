<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The HTTP header field grammar: RFC 9110 token names and field-values,
 * plus the value normalization both header mutation paths consult.
 *
 * Pure static helper -- the same shape as the URI grammars. Every
 * assertion fails closed with the layer's exception, byte-identical to
 * the reference implementation (legacy `zef-framework`
 * `Domain\Validation\HeaderValidator`): the name must be a non-empty
 * token, the value may carry HTAB, VCHAR and obs-text but no other
 * control characters, and a non-string array member is rejected instead
 * of silently coerced.
 */
final class HeaderGrammar
{
    private const string NAME_PATTERN = '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/';

    private const string VALUE_FORBIDDEN_PATTERN = '/[^\t\x20-\x7E\x80-\xFF]/';

    /**
     * The SAPI emission path's acceptance question: whether one header
     * field may be transmitted at all.
     *
     * The emitter's boolean twin of {@see normalize()}: the emission path
     * judges fields it has already normalised to strings and must skip
     * an unacceptable one without failing the response -- the emitter
     * keeps shipping the well-formed neighbours -- so the gate answers
     * with a boolean instead of throwing.
     */
    public static function acceptable(string $name, string $value): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1
            && preg_match(self::VALUE_FORBIDDEN_PATTERN, $value) !== 1;
    }

    /**
     * Validates a header name and normalizes its raw value(s) into a
     * list of validated strings.
     *
     * The value is deliberately `mixed` at the boundary: PSR-7 declares
     * the parameter without a type, so the runtime checks are the
     * contract's enforcement point, not redundancy.
     *
     * @param mixed $value string or list<string> per the PSR contract;
     *                     anything else fails closed
     *
     * @return list<string>
     *
     * @throws HttpException when the name is not a valid RFC 9110 token,
     *                       a member of the value is not a string, or a
     *                       value carries forbidden control characters
     */
    public static function normalize(string $name, mixed $value): array
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new HttpException("Invalid header name '{$name}'.");
        }

        $values = \is_array($value) ? $value : [$value];
        $normalized = [];
        foreach ($values as $each) {
            if (!\is_string($each)) {
                throw new HttpException('Header value must be a string.');
            }

            if (preg_match(self::VALUE_FORBIDDEN_PATTERN, $each) === 1) {
                throw new HttpException("Invalid header value for '{$name}'.");
            }

            $normalized[] = $each;
        }

        return $normalized;
    }
}
