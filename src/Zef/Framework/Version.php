<?php

declare(strict_types=1);

namespace Zef\Framework;

/**
 * Single source of truth for the framework version string.
 *
 * Exposed as a class (rather than only via the Composer package metadata) so
 * that the kernel, the health endpoints and the CLI can report a version without
 * reading `composer.json` at runtime.
 */
final class Version
{
    public const string VERSION = '0.1.0-dev';

    public static function current(): string
    {
        return self::VERSION;
    }
}
