<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;

/**
 * The session family's identifier core: the cryptographically secure
 * session-id generator the Zero-Library Shim's own promise spells --
 * a replacement for `session_id()` that mints its entropy from
 * `random_bytes()` and never starts a native PHP session, because the
 * family owns the whole session lifecycle itself (the adapter port is
 * the storage, the cookie is the transport, and PHP's own session
 * machinery is nowhere in the process).
 *
 * An identifier is the raw entropy folded through base64url without
 * padding -- URL-safe in a cookie value, the same alphabet the CSRF
 * family's token bodies ride ({@see CsrfTokenManager}) -- so 32 bytes
 * (the policy's default) spells as 43 characters. Issuance mints
 * fresh randomness per identifier, so two identifiers from one seam
 * never collide.
 *
 * The incoming grammar ({@see self::isValid()}) checks the alphabet
 * and a length corridor that covers every spelling the entropy
 * corridor (16..64 bytes) can fold into (22..86 characters). The
 * corridor is deliberately the whole corridor, not the exact length
 * this deployment issues: a valid-shaped identifier the adapter does
 * not know answers a fresh session (the miss is the truth), while a
 * tighter grammar would gain nothing the adapter lookup does not
 * already decide -- and a hostile overlong identifier is already
 * refused by the corridor's ceiling before any storage is asked.
 * A malformed identifier never reaches the adapter at all: it is
 * treated as an absent one, and the request answers a freshly minted
 * session rather than a refusal, because a stale or hand-mangled
 * cookie is a client condition every deployment meets, not a
 * server-side fault the error boundary should answer.
 */
final readonly class SessionId
{
    /** The identifier's alphabet: base64url, no padding. */
    public const string GRAMMAR = '/^[A-Za-z0-9_-]{22,86}$/D';

    /** The entropy floor this class holds at its own seam, the
     * CsrfTokenManager's own discipline. */
    private const int MIN_ID_BYTES = 16;

    /**
     * @param int                         $idBytes the entropy each issued identifier
     *                                             carries, in bytes (the floor this
     *                                             class holds itself; the corridor
     *                                             the policy's bounds assert)
     * @param null|(Closure(int): string) $entropy the random-byte seam,
     *                                             injectable so the suite can pin
     *                                             issuance; defaults to the engine's own
     *                                             {@see random_bytes()}
     */
    public function __construct(
        private int $idBytes = 32,
        private ?Closure $entropy = null,
    ) {
        if ($this->idBytes < self::MIN_ID_BYTES) {
            throw new HttpException(
                'The session identifier entropy must be at least ' . self::MIN_ID_BYTES . ' bytes.',
            );
        }
    }

    /**
     * Mints one fresh session identifier.
     */
    public function issue(): string
    {
        return mb_rtrim(strtr(base64_encode($this->randomBytes($this->idBytes)), '+/', '-_'), '=');
    }

    /**
     * Answers whether one carried value spells as an identifier this
     * family could have issued: the base64url alphabet inside the
     * length corridor.
     */
    public static function isValid(string $value): bool
    {
        return preg_match(self::GRAMMAR, $value) === 1;
    }

    /**
     * The identifier's fresh random bytes: the entropy the knob asked
     * for, under the floor the constructor holds.
     */
    private function randomBytes(int $bytes): string
    {
        \assert($bytes >= self::MIN_ID_BYTES, 'the constructor holds the entropy floor.');

        return ($this->entropy ?? random_bytes(...))($bytes);
    }
}
