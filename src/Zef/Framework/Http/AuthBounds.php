<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's bounds: the range invariants the family holds,
 * asserted at the two layers that own them -- following the CSRF
 * family's own {@see CsrfBounds} two-seam lineage.
 *
 * The token shape is the policy's own ({@see AuthPolicy}): each
 * static token must be a grammar-valid bearer credential of at least
 * 32 and at most 512 characters. The reference carried no entropy
 * floor at all (any non-empty token was table material -- a
 * brute-forceable credential accepted as a posture), and this port
 * answers that the way the CSRF family answered its signing secret:
 * a short token is a configuration error that halts the boot with the
 * knob named, not a weaker boundary the operator never asked to
 * weaken. The ceiling is the credential handle's own ({@see
 * AuthCredential::MAX_ID_CHARS}), the one bound held on both sides of
 * the extraction seam.
 *
 * The token table's presence is the resolution's own ({@see
 * AuthFactory}): an armed family with an empty table is the CSRF
 * secret's own posture -- the empty default is the "not configured"
 * an ARMED family halts on at boot, with the remediation spelled out,
 * because stack membership is already the explicit opt-in and a
 * silently tokenless boundary is a boundary the operator believes is
 * running. The disarm the operator wants is a spelling they can see:
 * `security.auth enabled = false`, or the middleware out of the
 * stack.
 */
final class AuthBounds
{
    /** The static token's entropy floor, in characters. */
    private const int MIN_TOKEN_CHARS = 32;

    /**
     * Asserts one static token's shape: the token68 grammar the
     * extraction accepts, over the floor and under the ceiling.
     *
     * @param string $token the table entry under the bound
     *
     * @throws HttpException when the token is malformed or out of
     *                       range
     */
    public static function assertTokenShape(string $token): void
    {
        if (mb_strlen($token) < self::MIN_TOKEN_CHARS) {
            throw new HttpException(
                'The security.auth tokens knob carries a token below the 32-character floor:'
                . ' static bearer tokens are brute-forceable below it.',
            );
        }

        if (mb_strlen($token) > AuthCredential::MAX_ID_CHARS) {
            throw new HttpException(
                'The security.auth tokens knob carries a token above the 512-character ceiling.',
            );
        }

        if (!AuthCredential::isBearerToken($token)) {
            throw new HttpException(
                'The security.auth tokens knob carries a token that is not a bearer credential'
                . ' (RFC 6750 token68: letters, digits, - . _ ~ + /, with = padding at the end only).',
            );
        }
    }

    /**
     * Asserts the armed family's token table is one it can admit
     * with, at the resolution the stack membership triggered.
     *
     * @param list<string> $tokens the family's static token table
     *
     * @throws HttpException when the table is empty
     */
    public static function assertUsableTokens(array $tokens): void
    {
        if ($tokens === []) {
            throw new HttpException(
                'The security.auth family is enabled but carries no static bearer tokens:'
                . ' provide at least one through the environment overlay'
                . ' (APP_SECURITY__AUTH__TOKENS, comma-separated, each 32 characters or above)'
                . ' or a configuration file, or disarm the family explicitly'
                . ' (security.auth enabled = false).',
            );
        }
    }
}
