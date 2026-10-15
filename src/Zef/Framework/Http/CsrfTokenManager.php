<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CSRF family's token core: HMAC-SHA256 issuance and verification for
 * the double-submit discipline {@see CsrfMiddleware} enforces.
 *
 * A token is two dot-separated parts: a random body (base64url, no
 * padding -- URL-safe in a cookie value and in a header field) and the
 * HMAC-SHA256 of that body under the family's signing secret. Issuance
 * mints fresh randomness per token, so two tokens from one manager never
 * collide; verification is constant-time twice over -- the shape gate
 * runs before any secret-dependent comparison, and the mac comparison is
 * {@see hash_equals()}, never `===` (a timing-observable comparison over
 * a signature is a partial-disclosure oracle). The shape gate is part of
 * the contract, not decoration: a value that fails the body grammar or
 * the signature grammar is rejected without the mac ever being computed,
 * so malformed input cannot use the manager as a hashing probe.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Security/CsrfTokenManager.php`, milestone E2), value for
 * value: the 32-byte minimum secret, the 16-byte minimum entropy, the
 * base64url alphabet, the 64-hex lowercase signature gate, the
 * `explode('.', ..., 2)` split with its empty-arm padding, and the
 * `hash_equals` comparison. Three deliberate deviations, all documented:
 * the reference's `issue()` re-clamped `tokenBytes` (`< 16 ? 16 : ...`)
 * although its own constructor already refused anything below 16 -- a
 * dead branch this port drops; the constructor's rejections are
 * {@see HttpException} (the layer's single exception root) rather than
 * the reference's `InvalidArgumentException` -- the policy and the boot
 * read them identically either way; and the verification's shape gate is
 * one clause, not four -- the reference tested the two empty arms
 * separately from the two grammar arms, but the grammars' `+` quantifier
 * already refuses the empty string, so the empty arms were a redundant
 * spelling of the grammar arms (and one whose `||` chain carried a
 * value-equivalent mutant under the grammar's own implication). Tokens
 * carry no expiry: the
 * reference signed nothing time-bound into the mac and this port keeps
 * that posture value for value (a rotation means a new secret, which
 * invalidates every issued token at once -- the operator's remediation,
 * not a per-token knob the reference never exposed).
 */
final readonly class CsrfTokenManager
{
    /** The token body's alphabet: base64url, no padding. */
    private const string BODY_GRAMMAR = '/^[A-Za-z0-9_-]+$/D';

    /** The signature's shape: HMAC-SHA256 as 64 lowercase hex digits. */
    private const string SIGNATURE_GRAMMAR = '/^[a-f0-9]{64}$/D';

    private const int MIN_SECRET_BYTES = 32;

    private const int MIN_TOKEN_BYTES = 16;

    public function __construct(
        private string $secret,
        private int $tokenBytes = 32,
    ) {
        if (mb_strlen($this->secret) < self::MIN_SECRET_BYTES) {
            throw new HttpException('The csrf signing secret must be at least 32 bytes.');
        }

        if ($this->tokenBytes < self::MIN_TOKEN_BYTES) {
            throw new HttpException('The csrf token entropy must be at least 16 bytes.');
        }
    }

    /**
     * Mints one fresh token: random body, its mac under the secret.
     */
    public function issue(): string
    {
        $token = mb_rtrim(strtr(base64_encode($this->entropy($this->tokenBytes)), '+/', '-_'), '=');

        return $token . '.' . $this->mac($token);
    }

    /**
     * Answers whether one token carries a well-formed body and the mac
     * this secret would have signed over it.
     */
    public function isValid(string $token): bool
    {
        $parts = explode('.', $token, 2);
        [$body, $signature] = array_pad($parts, 2, '');

        if (
            preg_match(self::BODY_GRAMMAR, $body) !== 1
            || preg_match(self::SIGNATURE_GRAMMAR, $signature) !== 1
        ) {
            return false;
        }

        return hash_equals($this->mac($body), $signature);
    }

    /**
     * The token body's fresh random bytes: the entropy the knob asked
     * for, under the floor the constructor holds.
     */
    private function entropy(int $bytes): string
    {
        \assert($bytes >= self::MIN_TOKEN_BYTES, 'the constructor holds the entropy floor.');

        return random_bytes($bytes);
    }

    /**
     * The HMAC-SHA256 of one token body under the signing secret.
     */
    private function mac(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }
}
