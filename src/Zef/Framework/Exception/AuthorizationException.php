<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when an authenticated caller lacks the entitlement an endpoint
 * demands (ROADMAP.md, "Error Handling System", phase 1: the exception
 * hierarchy) -- the identity question was already answered, the
 * permission question was not.
 *
 * The entitlement signal (403): the caller is known and still may not
 * proceed. The identity signal is the separate
 * {@see AuthenticationException} (401); folding the two would answer an
 * anonymous caller with a refusal that ends the conversation instead of
 * inviting credentials.
 */
final class AuthorizationException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        return 403;
    }
}
