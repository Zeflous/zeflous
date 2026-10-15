<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The authentication resolution: what a provider answered, with the
 * constructor invariants that make the answer self-consistent --
 * move-verbatim from the reference's `AuthenticationResult` (legacy
 * `zef-main` `Framework/Security/Distributed/AuthenticationResult.php`).
 *
 * The two invariants are the load-bearing part: only
 * {@see AuthStatus::AUTHENTICATED} carries a context, and every other
 * status carries none. The reference's boundary kept a
 * malformed-metadata arm for the authenticated-without-context shape
 * these invariants already forbid; this port drops the dead arm and
 * keeps the invariants ({@see AuthBoundary} -- an unreachable branch
 * is not defence in depth, it is a branch no test can reach).
 */
final readonly class AuthResult
{
    /**
     * @throws HttpException when the status and the context disagree
     */
    public function __construct(
        public AuthStatus $status,
        public ?AuthContext $context = null,
    ) {
        if ($this->status === AuthStatus::AUTHENTICATED && !$this->context instanceof AuthContext) {
            throw new HttpException('The security.auth authenticated result requires a context.');
        }

        if ($this->status !== AuthStatus::AUTHENTICATED && $this->context instanceof AuthContext) {
            throw new HttpException('The security.auth unauthenticated result cannot carry a context.');
        }
    }
}
