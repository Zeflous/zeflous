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
 * malformed-metadata arm is gone, because the
 * authenticated-without-context shape it answered is unreachable
 * through {@see AuthResult}'s constructor invariant in either
 * implementation -- the reference carried a branch no test could
 * reach, and this port refuses dead arms.
 *
 * `UNAUTHENTICATED` keeps the reference's own mapping: the
 * authentication-failed refusal. A provider that answers the
 * anonymous handle with `UNAUTHENTICATED` is refusing to vouch for
 * the anonymous principal, which is the default posture of the
 * shipped static table ({@see StaticCredentialProvider}).
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
        $authResult = $this->credentialProvider->resolve(
            $authCredential ?? AuthCredential::anonymous(),
            $nowMs,
        );

        if ($authResult->status !== AuthStatus::AUTHENTICATED) {
            return $this->refused(AuthFailure::unauthenticated($authResult->status));
        }

        \assert($authResult->context instanceof AuthContext, 'an authenticated result carries its context.');

        $authorizationResult = $this->authorizationPolicy->authorize($authResult->context, $authRequest);

        if ($authorizationResult->verdict !== AuthVerdict::ALLOW) {
            return $this->refused(AuthFailure::AUTHORIZATION_DENIED);
        }

        $replayDecision = $this->replayProtector->check($authRequest->replayId, $nowMs);

        if (!$replayDecision->allows()) {
            return $this->refused(AuthFailure::replay($replayDecision));
        }

        return new AuthAdmission(AuthVerdict::ALLOW, AuthFailure::NONE, false, $authResult->context);
    }

    /**
     * One refusal's admission shape.
     */
    private function refused(AuthFailure $authFailure): AuthAdmission
    {
        return new AuthAdmission(AuthVerdict::DENY, $authFailure, false);
    }
}
