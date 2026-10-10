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
 * a {@see ConfigException} that names the offending path: a basename outside
 * the allowlist, an unresolvable path, a directory, another extension, or a
 * scalar / null / missing return value are all errors, never a silent
 * fallback to an empty configuration.
 *
 * Executing a file is the strongest operation a loader performs, so the path
 * is gated twice before the `require`: only a file whose basename is listed
 * in the constructor's allowlist may execute (a declared inventory, not
 * whatever a caller assembles), and the path must resolve through
 * `realpath()` to a canonical absolute file (stream-wrapper URLs and other
 * non-resolvable spellings are rejected). The regular-file and `.php`
 * extension guarantees are then checked against the resolved path, so the
 * `require` executes the canonical file and every downstream message names
 * it.
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
final readonly class PhpFileLoader implements ConfigLoaderInterface
{
    /** @var array<non-empty-string, non-empty-string> */
    private array $allowedFiles;

    /**
     * @param non-empty-list<non-empty-string> $allowedFiles basenames that this loader may execute
     */
    public function __construct(array $allowedFiles)
    {
        $lookup = [];

        foreach ($allowedFiles as $allowedFile) {
            $lookup[$allowedFile] = $allowedFile;
        }

        $this->allowedFiles = $lookup;
    }

    #[Override]
    public function load(string $path): array
    {
        $filename = basename($path);

        if (!isset($this->allowedFiles[$filename])) {
            throw new ConfigException(
                \sprintf('The configuration file "%s" is not allowed.', $path),
            );
        }

        $filePath = realpath($path);

        if (!\is_string($filePath)) {
            throw new ConfigException(
                \sprintf('The configuration file "%s" could not be resolved.', $path),
            );
        }

        if (!is_file($filePath)) {
            throw new ConfigException(
                \sprintf('The configuration file "%s" is not a regular file.', $filePath),
            );
        }

        if (!str_ends_with($filePath, '.php')) {
            throw new ConfigException(
                \sprintf('The configuration file "%s" does not use the supported .php extension.', $filePath),
            );
        }

        return $this->asArray(require $filePath, $filePath);
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
