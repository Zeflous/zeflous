<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The boundary's admission decision: the verdict, the failure it
 * refused over, whether a retry could succeed, and -- the port's one
 * addition -- the authenticated principal's context on the allowed
 * side -- ported from the reference's `SecurityAdmissionDecision`
 * (legacy `zef-main` `Framework/Security/Distributed/
 * SecurityAdmissionDecision.php`).
 *
 * The constructor invariants are move-verbatim: an allowed admission
 * carries no failure, a denied one permits no retry. The context
 * addition is the restructured boundary's own seam ({@see
 * AuthBoundary} composes the provider, the policy and the protector
 * once, where the reference passed the policy and protector through
 * every `admit()` call and read the resolution straight off its
 * middleware-held result): the allowed admission is the middleware's
 * single answer, so it carries what the middleware stamps -- the
 * context whose principal id becomes the request's attribute.
 *
 * `retryAllowed` stays the reference's contract surface even though
 * this boundary always answers it false: a decision that could
 * honestly offer a retry is a legitimate future arm, and the
 * invariant (denied implies no retry) is what keeps such an arm
 * honest when it lands.
 */
final readonly class AuthAdmission
{
    /**
     * @throws HttpException when the verdict and the failure, or the
     *                       verdict and the retry flag, disagree
     */
    public function __construct(
        public AuthVerdict $verdict,
        public AuthFailure $failure,
        public bool $retryAllowed,
        public ?AuthContext $context = null,
    ) {
        if ($this->verdict === AuthVerdict::ALLOW && $this->failure !== AuthFailure::NONE) {
            throw new HttpException('The security.auth allowed admission cannot carry a failure.');
        }

        if ($this->verdict === AuthVerdict::DENY && $this->retryAllowed) {
            throw new HttpException('The security.auth denied admission cannot allow a retry.');
        }

        if ($this->verdict === AuthVerdict::ALLOW && !$this->context instanceof AuthContext) {
            throw new HttpException('The security.auth allowed admission requires a context.');
        }
    }

    /**
     * Whether the decision admits the request.
     */
    public function allows(): bool
    {
        return $this->verdict === AuthVerdict::ALLOW;
    }
}
