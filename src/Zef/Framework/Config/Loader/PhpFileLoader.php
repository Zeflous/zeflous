<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Loader;

use Override;
use Zef\Framework\Config\ConfigException;

/**
 * Configuration adapter for PHP configuration files (the native format).
 *
 * A PHP configuration file is a regular file whose name ends in `.php` and
 * whose execution returns an array -- the convention the zero-dependency
 * configuration layer fixes as its first format (see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree"). Anything else fails closed with
 * a {@see ConfigException} that names the offending path: a missing file, a
 * directory, another extension, or a scalar / null / missing return value are
 * all errors, never a silent fallback to an empty configuration.
 *
 * The file is executed with `require` (not `require_once`/`include_once`)
 * deliberately: a configuration file must be re-loadable within one process
 * -- loading the same file under two prefixes, or re-loading after a cache
 * miss, must return the array again. The `*_once` forms return `true` for an
 * already-executed file, which would turn every legitimate re-load into a
 * spurious "must return an array" error. The existence of the file is proven
 * before the call, so `require`'s engine-level failure only remains for the
 * unreadable-file edge, which halts the bootstrap either way.
 */
final class PhpFileLoader implements ConfigLoaderInterface
{
    #[Override]
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new ConfigException(
                \sprintf('The configuration file "%s" is not a regular file.', $path),
            );
        }

        if (!str_ends_with($path, '.php')) {
            throw new ConfigException(
                \sprintf('The configuration file "%s" does not use the supported .php extension.', $path),
            );
        }

        return $this->asArray(require $path, $path);
    }

    /**
     * Narrows the raw require result to a configuration array.
     *
     * The raw result is passed straight into the `mixed` parameter and
     * narrowed here, which keeps the calling scope free of `mixed` assignments.
     *
     * @return array<array-key, mixed>
     */
    private function asArray(mixed $value, string $path): array
    {
        if (!\is_array($value)) {
            throw new ConfigException(
                \sprintf('The configuration file "%s" must return an array.', $path),
            );
        }

        return $value;
    }
}
