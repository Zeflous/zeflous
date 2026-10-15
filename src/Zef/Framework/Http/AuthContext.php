<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The authenticated principal's context: what a provider vouched for
 * when it resolved a credential, move-verbatim from the reference's
 * `SecurityContext` (legacy `zef-main` `Framework/Security/Distributed/
 * SecurityContext.php`) -- every field bounded exactly as the
 * reference held it, and the bounds are the authorization policy's
 * contract surface ({@see AllowScopePolicy} reads the principal id
 * and the credential scope).
 *
 * The port keeps the reference's five fields: `principalId` (who),
 * `authenticationMethod` (how -- a provider-declared vocabulary),
 * `authorizationContext` (where the authorisation applies -- the
 * reference's own static provider spelled `route`), `credentialScope`
 * (the comma-separated grant list the scope policy reads), and
 * `peerIdentity` (the transport-level identity, nullable because a
 * bearer credential implies none).
 */
final readonly class AuthContext
{
    /** The principal id's ceiling. */
    public const int MAX_PRINCIPAL_CHARS = 128;

    /** The authentication method's ceiling. */
    public const int MAX_AUTH_METHOD_CHARS = 64;

    /** The authorization context's ceiling. */
    public const int MAX_AUTHZ_CONTEXT_CHARS = 256;

    /** The credential scope's ceiling. */
    public const int MAX_CREDENTIAL_SCOPE_CHARS = 128;

    /** The peer identity's ceiling. */
    public const int MAX_PEER_IDENTITY_CHARS = 128;

    /**
     * @throws HttpException when a field violates its bound
     */
    public function __construct(
        public string $principalId,
        public string $authenticationMethod,
        public string $authorizationContext,
        public string $credentialScope,
        public ?string $peerIdentity,
    ) {
        $this->assertBounded($this->principalId, self::MAX_PRINCIPAL_CHARS, 'principalId');
        $this->assertBounded($this->authenticationMethod, self::MAX_AUTH_METHOD_CHARS, 'authenticationMethod');
        $this->assertBounded($this->authorizationContext, self::MAX_AUTHZ_CONTEXT_CHARS, 'authorizationContext');
        $this->assertBounded($this->credentialScope, self::MAX_CREDENTIAL_SCOPE_CHARS, 'credentialScope');

        if ($this->peerIdentity !== null) {
            $this->assertBounded($this->peerIdentity, self::MAX_PEER_IDENTITY_CHARS, 'peerIdentity');
        }
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
            throw new HttpException('The security.auth context ' . $field . ' exceeds its bound.');
        }
    }
}
