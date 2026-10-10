<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Loader;

use Zef\Framework\Config\DotKey;
use Zef\Framework\Config\DotWriter;

/**
 * Director of the configuration loading pipeline.
 *
 * Named constructors address the two supported sources -- a single PHP
 * configuration file and a directory of PHP configuration files -- with an
 * optional dot-notation prefix that nests the loaded array under a top-level
 * key (the prefix-loading ergonomics of `PHLAK/Config`, adapted to the ZEF
 * doctrine). The director is deliberately thin: source validity and the
 * fail-closed semantics live in the adapters themselves, and a malformed
 * prefix is rejected by the shared dot-key mechanics.
 *
 * The director is the configuration layer's build-time entry point: the
 * layered builder and the kernel bootstrapper consume it, and host
 * applications may call it directly, which is why it is marked `@api` rather
 * than being baselined or suppressed.
 *
 * @api
 */
final readonly class ConfigLoader
{
    /**
     * Loads a single PHP configuration file, optionally nested under a prefix.
     *
     * @return array<array-key, mixed>
     */
    public static function file(string $path, string $prefix = ''): array
    {
        return self::prefixed(new PhpFileLoader()->load($path), $prefix);
    }

    /**
     * Loads a directory of PHP configuration files, optionally nested under a
     * prefix.
     *
     * @return array<array-key, mixed>
     */
    public static function directory(string $path, string $prefix = ''): array
    {
        return self::prefixed(new DirectoryLoader()->load($path), $prefix);
    }

    /**
     * Nests the loaded data under the dot-notation prefix; an empty prefix
     * returns the data unchanged.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function prefixed(array $data, string $prefix): array
    {
        if ($prefix === '') {
            return $data;
        }

        return DotWriter::set([], DotKey::parse($prefix), $data);
    }
}
