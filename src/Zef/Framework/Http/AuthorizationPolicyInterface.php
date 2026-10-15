<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The authorization contract: how the boundary asks the application's
 * policy whether an authenticated context may perform the request --
 * ported from the reference's `AuthorizationPolicyInterface` (legacy
 * `zef-main` `Framework/Security/Distributed/
 * AuthorizationPolicyInterface.php`).
 *
 * The policy runs only for an authenticated principal -- the
 * boundary's order ({@see AuthBoundary}) resolves authentication,
 * then authorizes, then replays -- so the context it reads is never
 * null and never anonymous unless the provider vouched for the
 * anonymous principal. The framework ships the scope grant ({@see
 * AllowScopePolicy}) as the config-driven spelling; applications
 * derive their own (RBAC, ABAC, a route table) through hand
 * construction of the family.
 */
interface AuthorizationPolicyInterface
{
    /**
     * Authorizes one authenticated context against one request.
     *
     * @param AuthContext $authContext the authenticated principal's context
     * @param AuthRequest $authRequest the request the policy is asked about
     */
    public function authorize(AuthContext $authContext, AuthRequest $authRequest): AuthorizationResult;
}
