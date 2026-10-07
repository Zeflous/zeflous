<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Zef\Framework\Exception\ZefException;

/**
 * Raised when a developer/CI tooling gate cannot evaluate its input.
 *
 * The tooling gates are fail-closed: a malformed or missing report is an error,
 * never a silent pass. This exception carries that error.
 */
final class ToolingException extends ZefException
{
}
