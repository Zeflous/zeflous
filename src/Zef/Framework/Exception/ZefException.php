<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use RuntimeException;

/**
 * Base class for every exception raised by the ZEF framework.
 *
 * Framework code must never throw a bare {@see RuntimeException} (or any other
 * SPL exception) directly: callers need a single, catchable root type so that a
 * host application can distinguish "the framework failed" from "my code failed".
 * Concrete failures extend this class.
 */
abstract class ZefException extends RuntimeException
{
}
