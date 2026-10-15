<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * The scope grant policy: the framework's own authorization policy,
 * the config-driven spelling of the reference's
 * `AllowScopeAuthorizationPolicy` (legacy `zef-main`
 * `Framework/Security/Distributed/AllowScopeAuthorizationPolicy.php`)
 * -- framework-only, exactly as the reference marked its own: an
 * application that grows real authorisation derives a policy from the
 * resource and action of {@see AuthRequest} (RBAC, ABAC, a route
 * table) and composes it through the boundary instead.
 *
 * The grant rule is move-verbatim: the anonymous principal passes
 * when the policy allows anonymous, any other principal passes when
 * its credential scope list contains the required scope -- the list
 * comma-separated, entries trimmed, empty entries dropped, compared
 * byte-for-byte (scope tokens are case-sensitive, the way RFC 6749
 * holds them). The principal fold is the reference's own
 * case-insensitive anonymous comparison.
 */
final readonly class AllowScopePolicy implements AuthorizationPolicyInterface
{
    /**
     * @throws HttpException when the required scope is empty
     */
    public function __construct(
        private string $requiredScope,
        private bool $allowAnonymous = false,
    ) {
        if ($this->requiredScope === '') {
            throw new HttpException('The security.auth required scope must not be empty.');
        }
    }

    #[Override]
    public function authorize(AuthContext $authContext, AuthRequest $authRequest): AuthorizationResult
    {
        if (mb_strtolower($authContext->principalId) === 'anonymous' && $this->allowAnonymous) {
            return new AuthorizationResult(AuthVerdict::ALLOW, 'anonymous-allowed');
        }

        if (\in_array($this->requiredScope, $this->grantedScopes($authContext->credentialScope), true)) {
            return new AuthorizationResult(AuthVerdict::ALLOW, 'scope-granted');
        }

        return new AuthorizationResult(AuthVerdict::DENY, 'scope-denied');
    }

    /**
     * The credential scope list: comma-separated, trimmed, empty
     * entries dropped -- the reference's own reading.
     *
     * @return list<string>
     */
    private function grantedScopes(string $credentialScope): array
    {
        $granted = [];

        foreach (explode(',', $credentialScope) as $scope) {
            $scope = mb_trim($scope);

            if ($scope === '') {
                continue;
            }

            $granted[] = $scope;
        }

        return $granted;
    }
}
