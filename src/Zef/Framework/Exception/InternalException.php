<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised to report a bug in the framework or the application as a
 * typed signal (ROADMAP.md, "Error Handling System", phase 1: the
 * exception hierarchy) -- the internal-fault signal (500).
 *
 * Throwing this class is a deliberate act: the author owns the message
 * and asserts it is safe to show, which is why it answers through the
 * typed family's public-message posture (redacted, but visible in
 * production) instead of the generic 500 title. An unexpected
 * throwable -- the fault nobody chose the words for -- still falls to
 * the untyped arm and its "An error occurred" production posture; the
 * distinction is the whole point of this class.
 */
final class InternalException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        return 500;
    }
}
