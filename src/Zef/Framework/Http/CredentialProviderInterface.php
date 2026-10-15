<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The credential resolution contract: how the boundary asks whatever
 * backs the application's credentials whether a handle is one of
 * theirs -- ported from the reference's `CredentialProviderInterface`
 * (legacy `zef-main` `Framework/Security/Distributed/
 * CredentialProviderInterface.php`).
 *
 * The provider owns the whole authentication answer: the status
 * ({@see AuthStatus}), the context an authenticated principal carries
 * ({@see AuthContext}), expiry, revocation, scope issuance -- the
 * middleware only extracts the line ({@see AuthCredential}) and hands
 * it over. The framework ships the static token table ({@see
 * StaticCredentialProvider}) as the config-driven spelling;
 * applications wire their own (a database, a JWT verifier, an OIDC
 * introspection endpoint) through hand construction of the family
 * ({@see AuthBoundary}).
 */
interface CredentialProviderInterface
{
    /**
     * Resolves one credential handle into an authentication result.
     *
     * @param AuthCredential $authCredential the extracted handle -- the
     *                                       bearer spelling, or the
     *                                       anonymous one a credential-less
     *                                       request resolves through
     * @param int            $nowMs          the request's clock, Unix
     *                                       milliseconds
     */
    public function resolve(AuthCredential $authCredential, int $nowMs): AuthResult;
}
