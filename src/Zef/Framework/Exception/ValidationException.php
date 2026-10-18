<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when user input fails the application's own validation rules
 * (ROADMAP.md, "Error Handling System", phase 1: the exception
 * hierarchy) -- the request is well-formed at the HTTP boundary but its
 * payload does not satisfy the endpoint's contract.
 *
 * The framework's own malformed-request refusals stay on the HTTP
 * layer's untyped {@see \Zef\Framework\Http\HttpException} root; this
 * class is the signal an application throws from its handlers, so its
 * message is written for the client by contract and answers 400.
 */
final class ValidationException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        return 400;
    }
}
