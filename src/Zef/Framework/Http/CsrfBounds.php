<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CSRF family's bounds: the two range invariants the family holds,
 * asserted at the two layers that own them.
 *
 * The entropy bound is the policy's own ({@see CsrfPolicy}): a
 * `tokenBytes` below 16 stops a token's body being collision-resistant,
 * and the policy's constructor refuses it -- ported without the
 * reference's silent `max(16, ...)` promotion, so a mistyped zero halts
 * with the knob named instead of quietly strengthening.
 *
 * The secret bound is the resolution's own ({@see CsrfFactory}): an
 * armed family with no usable secret is the one posture the reference
 * answered two different ways (a loud throw for an explicit opt-in, a
 * quiet `error_log` plus silent disable for its default-on arm), and
 * this port answers it one way -- the halt with the remediation spelled
 * out. The halt belongs to the resolution rather than the policy
 * because the policy is data: an absent family, an empty map and the
 * shipped profile all read as the default posture with an empty secret,
 * none of them an opt-in -- the stack membership that resolves the
 * `security.csrf` service is the explicit opt-in (the same line the
 * reference could only detect through environment-variable presence),
 * and a silent disable at that point is a protection the operator
 * believes is running while it is not (the same failure the rate-limit
 * store selection refuses, KDD-RL1 in `docs/ci-notes/security-runtime
 * .md`). The disarm the operator wants is a spelling they can see:
 * `security.csrf enabled = false`, or the middleware out of the stack.
 */
final class CsrfBounds
{
    private const int MIN_TOKEN_BYTES = 16;

    private const int MIN_SECRET_BYTES = 32;

    /**
     * Asserts the per-token entropy's floor, at the policy's
     * construction.
     *
     * @param int $tokenBytes the per-token entropy, in bytes
     *
     * @throws HttpException when the entropy is below the floor
     */
    public static function assert(int $tokenBytes): void
    {
        if ($tokenBytes < self::MIN_TOKEN_BYTES) {
            throw new HttpException('The security.csrf token_bytes knob must be 16 or above.');
        }
    }

    /**
     * Asserts the armed family's signing secret is one it can sign with,
     * at the resolution the stack membership triggered.
     *
     * @param string $secret the signing secret the family carries
     *
     * @throws HttpException when the secret is below the 32-byte floor
     */
    public static function assertUsableSecret(string $secret): void
    {
        if (mb_strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new HttpException(
                'The security.csrf family is enabled but carries no usable signing secret:'
                . ' provide one through the environment overlay (APP_SECURITY__CSRF__SECRET,'
                . ' at least 32 bytes) or a configuration file, or disarm the family explicitly'
                . ' (security.csrf enabled = false).',
            );
        }
    }
}
