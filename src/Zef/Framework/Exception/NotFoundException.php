<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when the addressed resource does not exist (ROADMAP.md, "Error
 * Handling System", phase 1: the exception hierarchy) -- the application
 * level's "nothing to answer" signal (404).
 *
 * An application throws this from its handlers when a route matched but
 * the entity behind the parameters is absent ("user with ID 123 not
 * found"); the router lane's own unmatched-path outcome owns the same
 * status through the dispatch translation, so the client sees one
 * 404 posture whether the miss happened at the pattern or behind it.
 */
final class NotFoundException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        return 404;
    }
}
