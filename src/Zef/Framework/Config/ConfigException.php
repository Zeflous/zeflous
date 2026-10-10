<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

use Zef\Framework\Exception\ZefException;

/**
 * Raised when configuration data is malformed or a configuration key cannot
 * be resolved.
 *
 * The configuration layer is fail-closed: a malformed key, a missing split
 * target or a type mismatch is an error, never a silent fallback (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree").
 */
final class ConfigException extends ZefException
{
}
