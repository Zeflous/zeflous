<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The authorization policy's answer: the verdict and the policy's own
 * code for it -- ported from the reference's `AuthorizationResult`
 * (legacy `zef-main` `Framework/Security/Distributed/
 * AuthorizationResult.php`), bound as the reference held it.
 *
 * The code is the policy's own vocabulary ({@see AllowScopePolicy}
 * answers `anonymous-allowed`, `scope-granted`, `scope-denied`), for
 * the application's logs and diagnostics -- the boundary reads the
 * verdict alone.
 */
final readonly class AuthorizationResult
{
    /** The policy code's ceiling, as the reference held it. */
    public const int MAX_CODE_CHARS = 128;

    /**
     * @throws HttpException when the code is empty or exceeds its
     *                       bound
     */
    public function __construct(
        public AuthVerdict $verdict,
        public string $policyCode,
    ) {
        if ($this->policyCode === '' || mb_strlen($this->policyCode) > self::MAX_CODE_CHARS) {
            throw new HttpException('The security.auth authorization code exceeds its bound.');
        }
    }
}
