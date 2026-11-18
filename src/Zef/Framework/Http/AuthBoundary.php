<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The distributed security boundary: the orchestration that resolves
 * a credential, authorizes the request and checks the replay id --
 * ported from the reference's `DefaultSecurityBoundary` (legacy
 * `zef-main` `Framework/Security/Distributed/DefaultSecurityBoundary.php`)
 * over its `SecurityBoundaryInterface` seam.
 *
 * The decision order is move-verbatim, the family's load-bearing
 * spine: authentication first (every non-authenticated status refuses
 * before a policy or a protector is consulted -- an unauthenticated
 * principal has no business discovering the authorisation surface),
 * authorisation second (the authenticated context against the
 * request), replay last (the admitted request must also be fresh).
 * The verdicts travel the reference's own failure taxonomy into the
 * admission ({@see AuthAdmission}).
 *
 * Two deliberate deviations, both argued in the port's lineage: the
 * boundary is composed once with its provider, policy and protector
 * as constructor collaborators (the reference passed the policy and
 * protector through every `admit()` call and read the resolution off
 * a middleware-held result -- the collaborators are wiring, not
 * per-request arguments, and the admission now carries the context
 * the allowed verdict vouches for); and the reference's
 * malformed-metadata arm answers a different shape here, because the
 * authenticated-without-context form it answered in the reference
 * is unreachable through {@see AuthResult}'s constructor invariant in
 * either implementation -- the reference carried a branch no test
 * could reach, and this port refuses dead arms, so the value's one
 * live arm is the reserved-name refusal argued below.
 *
 * `UNAUTHENTICATED` keeps the reference's own mapping: the
 * authentication-failed refusal. A provider that answers the
 * anonymous handle with `UNAUTHENTICATED` is refusing to vouch for
 * the anonymous principal, which is the default posture of the
 * shipped static table ({@see StaticCredentialProvider}).
 *
 * One deliberate tightening over the reference, argued from the
 * policy's own proxy (issue #785's finding 4): the anonymous
 * principal's name is reserved for the anonymous resolution. The
 * authorization policy finds the anonymous branch by folding the
 * context's principal id ({@see AllowScopePolicy}) -- a provider that
 * resolves a *presented* credential into a principal named
 * `anonymous` (any case fold) would steer an authenticated principal
 * into that branch and its blanket allowance, without any scope.
 * The boundary holds both facts the policy cannot: which handle the
 * credential travelled under and which name the provider answered.
 * A credential-bearing resolution under the reserved name is
 * refused as the metadata malformation it is ({@see
 * AuthFailure::MALFORMED_METADATA} -- the taxonomy's contract
 * vocabulary, whose live arm this now is). The credential-less
 * resolution keeps the name: the anonymous handle the boundary
 * itself constructed is the one spelling a provider may legitimately
 * answer `anonymous` for ({@see StaticCredentialProvider}'s own
 * anonymous context).
 */
final readonly class AuthBoundary
{
    /**
     * @param CredentialProviderInterface  $credentialProvider  the
     *                                                          authentication authority
     * @param AuthorizationPolicyInterface $authorizationPolicy the
     *                                                          authorisation rule
     * @param ReplayProtectorInterface     $replayProtector     the
     *                                                          freshness authority
     */
    public function __construct(
        private CredentialProviderInterface $credentialProvider,
        private AuthorizationPolicyInterface $authorizationPolicy,
        private ReplayProtectorInterface $replayProtector,
    ) {
    }

    /**
     * Admits one request: resolve, authorize, check freshness.
     *
     * @param null|AuthCredential $authCredential the extracted bearer
     *                                            credential, or null when the
     *                                            request carried none (the
     *                                            anonymous handle resolves)
     * @param AuthRequest         $authRequest    the request as the policy
     *                                            and the protector read it
     * @param int                 $nowMs          the request's clock, Unix
     *                                            milliseconds
     */
    public function admit(?AuthCredential $authCredential, AuthRequest $authRequest, int $nowMs): AuthAdmission
    {
        $handle = $authCredential ?? AuthCredential::anonymous();

        $authResult = $this->credentialProvider->resolve(
            $handle,
            $nowMs,
        );

        if ($authResult->status !== AuthStatus::AUTHENTICATED) {
            return $this->refused(AuthFailure::unauthenticated($authResult->status));
        }

        \assert($authResult->context instanceof AuthContext, 'an authenticated result carries its context.');

        if (ReservedPrincipalName::claimedBeyondTheAnonymousResolution($handle, $authResult->context->principalId)) {
            return $this->refused(AuthFailure::MALFORMED_METADATA);
        }

        return $this->authorized($authResult->context, $authRequest, $nowMs);
    }

    /**
     * The authenticated principal's own gauntlet: the authorisation
     * rule first, the replay window second -- each refusal answering
     * before the next collaborator is consulted.
     */
    private function authorized(AuthContext $authContext, AuthRequest $authRequest, int $nowMs): AuthAdmission
    {
        $authorizationResult = $this->authorizationPolicy->authorize($authContext, $authRequest);

        if ($authorizationResult->verdict !== AuthVerdict::ALLOW) {
            return $this->refused(AuthFailure::AUTHORIZATION_DENIED);
        }

        $replayDecision = $this->replayProtector->check($authRequest->replayId, $nowMs);

        if (!$replayDecision->allows()) {
            return $this->refused(AuthFailure::replay($replayDecision));
        }

        return new AuthAdmission(AuthVerdict::ALLOW, AuthFailure::NONE, false, $authContext);
    }

    /**
     * One refusal's admission shape.
     */
    private function refused(AuthFailure $authFailure): AuthAdmission
    {
        return new AuthAdmission(AuthVerdict::DENY, $authFailure, false);
    }
}
