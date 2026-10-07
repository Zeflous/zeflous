<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

/**
 * Raised when an internal invariant that the framework relies on is violated.
 *
 * This is a programming-error signal (not a user-input error): it means the
 * framework observed a state it considers impossible, so callers should treat it
 * as a defect rather than a recoverable condition.
 */
final class InvariantViolationException extends ZefException
{
}
