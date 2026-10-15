<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The bearer credential handle: what the middleware extracted from
 * the `Authorization` header, as the credential provider receives it
 * -- ported from the reference's `CredentialHandle` (legacy
 * `zef-main` `Framework/Security/Distributed/CredentialHandle.php`),
 * with the extraction grammar the reference spread between its
 * middleware and nothing at all tightened into this one class.
 *
 * The two spellings are the named constructors, so every handle that
 * exists passed the same bounds:
 *
 * - {@see self::fromBearerLine()} -- the RFC 6750 bearer line. The
 *   port is deliberately stricter than the reference, whose
 *   `/^Bearer\s+(.+)$/i` accepted any whitespace as the separator
 *   (RFC 7235's credentials grammar allows `1*SP` -- a tab is not a
 *   separator, it is a malformed line), any bytes as the token (the
 *   same RFC's `token68` grammar is `[A-Za-z0-9\-._~+/]+=*` -- the
 *   base64-ish vocabulary a bearer credential travels in, so a
 *   header smuggling a comma-joined second challenge, a quoted
 *   string or a control byte is not a credential at all), and up to
 *   512 bytes while the reference's own handle bound was 128 -- a
 *   129-byte token the extraction accepted crashed the boundary's
 *   constructor mid-request. The port holds one bound, 512, on both
 *   sides of the seam, and folds the field value's surrounding OWS
 *   first (RFC 9110 keeps OWS out of the field value). A line that
 *   is absent, empty, another scheme, or malformed in any of these
 *   ways answers null -- the request carries no credential, which
 *   the middleware treats as anonymous, never as a crash.
 *
 * - {@see self::anonymous()} -- the handle a credential-less request
 *   resolves through, move-verbatim from the reference's own
 *   construction (`anonymous` / `public` / never-expires).
 *
 * The expiry the handle carries is the client's claim, not the
 * framework's decision: the reference passed `PHP_INT_MAX` for the
 * bearer spelling (the provider owns expiry) and zero for the
 * anonymous one, and both spellings keep their values.
 */
final readonly class AuthCredential
{
    /** The credential id's ceiling, held on both sides of the extraction seam. */
    public const int MAX_ID_CHARS = 512;

    /** The claimed scope's ceiling, as the reference held it. */
    public const int MAX_SCOPE_CHARS = 128;

    /**
     * The bearer line's whole grammar: the case-insensitive scheme,
     * the `1*SP` separator, and the token68 vocabulary with its
     * trailing-only padding, captured as the credential id.
     */
    private const string BEARER_GRAMMAR = '/^Bearer +([A-Za-z0-9\-._~+\/]+=*)$/Di';

    /**
     * The token68 vocabulary alone: the shape a static table entry
     * must carry, the same bytes a presented line can match.
     */
    private const string TOKEN_GRAMMAR = '/^[A-Za-z0-9\-._~+\/]+=*$/D';

    /**
     * @throws HttpException when a bound is violated
     */
    public function __construct(
        public string $handleId,
        public string $scope,
        public int $expiresAtMs,
    ) {
        $this->assertBounded($this->handleId, self::MAX_ID_CHARS, 'handleId');
        $this->assertBounded($this->scope, self::MAX_SCOPE_CHARS, 'scope');

        if ($this->expiresAtMs < 0) {
            throw new HttpException('The security.auth credential expiry must be 0 or above.');
        }
    }

    /**
     * Reads the bearer credential out of the raw `Authorization`
     * header line, null when the line carries none.
     *
     * @param string $authorization the header line as PSR-7 joins it
     *                              (a comma joins multiple headers,
     *                              which the token68 grammar refuses)
     */
    public static function fromBearerLine(string $authorization): ?self
    {
        if (preg_match(self::BEARER_GRAMMAR, mb_trim($authorization), $match) !== 1) {
            return null;
        }

        if (mb_strlen($match[1]) > self::MAX_ID_CHARS) {
            return null;
        }

        return new self($match[1], 'bearer', \PHP_INT_MAX);
    }

    /**
     * The credential-less request's handle: the anonymous resolution
     * the provider is asked to vouch for or refuse.
     */
    public static function anonymous(): self
    {
        return new self('anonymous', 'public', 0);
    }

    /**
     * Whether this handle is the anonymous one -- the pair the
     * middleware constructs for a credential-less request, never a
     * spelling a presented line can produce (the extraction claims
     * the scope `bearer`, and the two words never meet).
     */
    public function isAnonymous(): bool
    {
        return $this->handleId === 'anonymous' && $this->scope === 'public';
    }

    /**
     * Whether a value is a bearer credential's own shape: the
     * token68 vocabulary, for the static table entries the policy
     * bounds ({@see AuthBounds}) -- a table entry that is not a
     * shape a presented line can match is a token no client could
     * ever present.
     */
    public static function isBearerToken(string $token): bool
    {
        return preg_match(self::TOKEN_GRAMMAR, $token) === 1;
    }

    /**
     * One field's non-empty bound, as the reference asserted it.
     *
     * @param string $value the field under the bound
     * @param int    $max   the field's ceiling, in characters
     * @param string $field the field's own name, as the rejection
     *                      spells it
     */
    private function assertBounded(string $value, int $max, string $field): void
    {
        if ($value === '' || mb_strlen($value) > $max) {
            throw new HttpException('The security.auth credential ' . $field . ' exceeds its bound.');
        }
    }
}
