<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session family's cookie-name vocabulary: the one token grammar
 * the `http.session.cookie_name` knob may name -- read as an RFC 9110
 * token, exactly as the CSRF family's own token names read ({@see
 * CsrfPolicy}).
 *
 * The fold mirrors the family's SameSite vocabulary ({@see
 * SameSiteVocabulary}): the grammar lives in one named place both the
 * config path and the constructor read through, instead of two
 * private spellings the policy carried -- the extraction the
 * architecture gate's difficulty ceiling asked for, kept ahead of
 * the ceiling instead of behind it.
 */
final class SessionCookieName
{
    /** The cookie-name grammar: an RFC 9110 token. */
    private const string TOKEN_NAME_GRAMMAR = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';

    /**
     * Reads one cookie-name value through the token grammar.
     *
     * @throws HttpException when the value is not an RFC 9110 token
     */
    public static function read(mixed $value): string
    {
        if (\is_string($value) && preg_match(self::TOKEN_NAME_GRAMMAR, $value) === 1) {
            return $value;
        }

        throw new HttpException(
            'The http.session cookie_name knob must be a cookie name (RFC 9110 token), '
            . (\is_string($value) ? "'" . $value . "'" : get_debug_type($value)) . ' given.',
        );
    }
}
