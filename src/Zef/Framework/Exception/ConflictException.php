<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when the request conflicts with the resource's current state
 * (ROADMAP.md, "Error Handling System", phase 1: the exception
 * hierarchy) -- the state-conflict signal (409).
 *
 * The classic shapes: creating an entity that already exists, or
 * mutating a resource whose state has moved since the caller last read
 * it (a stale optimistic-concurrency version). The request itself is
 * valid and the caller entitled; the state says no, and the client must
 * resolve the conflict before retrying.
 */
final class ConflictException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        return 409;
    }
}
