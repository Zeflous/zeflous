<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when a request is not authenticated -- the caller must prove
 * who they are before the endpoint will answer (ROADMAP.md, "Error
 * Handling System", phase 1: the exception hierarchy).
 *
 * This is the identity signal (401): an anonymous or failed-credential
 * request. Once identity is proven, the separate entitlement question
 * belongs to {@see AuthorizationException} (403) -- the two must never
 * be folded, because a 401 invites credentials while a 403 closes the
 * conversation.
 */
final class AuthenticationException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        return 401;
    }
}
