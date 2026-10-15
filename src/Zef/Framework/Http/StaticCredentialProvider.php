<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The static token table: the framework's own credential provider,
 * the config-driven spelling of the reference's
 * `StaticCredentialProvider` (legacy `zef-main`
 * `Framework/Security/Distributed/StaticCredentialProvider.php`).
 *
 * Every bearer token the table carries resolves to the one configured
 * principal and scope. The reference's own warning travels with the
 * class: a static table is bootstrap-grade authentication, the
 * deployment that grows real users wires a real provider through the
 * boundary instead. Two deliberate tightenings over the reference,
 * argued like the CSRF family's secret floor: each token must clear
 * 32 characters of length (the reference accepted any non-empty
 * token -- a brute-forceable credential is a configuration error,
 * not a posture), and the table comparison is constant-time per
 * candidate ({@see hash_equals()} -- the reference's `in_array()`
 * scan is a timing oracle over the table's order and prefix shape,
 * exactly the side channel the function exists to close). The token
 * grammar itself was already validated at the policy read ({@see
 * AuthPolicy}), so the table here carries only spellings a presented
 * line can actually match.
 *
 * The anonymous handle is this provider's one branching question, the
 * reference's own semantics kept whole: the provider is the authority
 * on whether the anonymous resolution is a principal it vouches for,
 * and the shipped answer is the `allow_anonymous` knob -- off, every
 * request through the boundary carries a credential (the reference's
 * own static wiring answered anonymous requests with the
 * authentication-failed refusal); on, a credential-less safe method
 * resolves as the anonymous principal, read-only by the middleware's
 * early 401 for unsafe methods.
 */
final readonly class StaticCredentialProvider implements CredentialProviderInterface
{
    /** The table principal's context, built once at composition. */
    private AuthContext $tableContext;

    /** The anonymous principal's context, built once at composition. */
    private AuthContext $anonymousContext;

    /**
     * @param list<string> $validTokens     the static token table, each
     *                                      entry a grammar-valid bearer
     *                                      token the policy already
     *                                      bounded
     * @param string       $principalId     the principal every table
     *                                      token resolves to
     * @param string       $credentialScope the scope granted to that
     *                                      principal
     * @param bool         $allowAnonymous  whether the anonymous handle
     *                                      resolves as the anonymous
     *                                      principal
     */
    public function __construct(
        private array $validTokens,
        string $principalId = 'api',
        string $credentialScope = 'api',
        private bool $allowAnonymous = false,
    ) {
        if ($this->validTokens === []) {
            throw new HttpException('The security.auth static token table requires at least one token.');
        }

        $this->tableContext = new AuthContext(
            $principalId,
            'static-bearer',
            'route',
            $credentialScope,
            null,
        );
        $this->anonymousContext = new AuthContext(
            'anonymous',
            'anonymous',
            'route',
            'public',
            null,
        );
    }

    #[Override]
    public function resolve(AuthCredential $authCredential, int $nowMs): AuthResult
    {
        foreach ($this->validTokens as $validToken) {
            if (hash_equals($validToken, $authCredential->handleId)) {
                return new AuthResult(AuthStatus::AUTHENTICATED, $this->tableContext);
            }
        }

        if ($authCredential->isAnonymous() && $this->allowAnonymous) {
            return new AuthResult(AuthStatus::AUTHENTICATED, $this->anonymousContext);
        }

        return new AuthResult(AuthStatus::FAILED);
    }
}
