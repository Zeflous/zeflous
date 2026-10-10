<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Loader;

use FilesystemIterator;
use Override;
use SplFileInfo;
use Zef\Framework\Config\ConfigException;

/**
 * Configuration adapter for a directory of PHP configuration files.
 *
 * Every direct `*.php` child becomes one top-level entry named after the file
 * minus its extension, so `config/app.php` yields the `app` key -- the flat
 * one-level layout the roadmap fixes for the configuration directory. The
 * scan is deliberately narrow and deterministic, and the file loader (and
 * with it the basename allowlist) is injected so the directory's inventory
 * stays an explicit, owner-declared list:
 *
 * - entries whose extension is not exactly `php` are skipped (documented, not
 *   an error: a configuration directory may hold notes and templates);
 * - hidden entries (a leading dot) are skipped, which also covers the `.php`
 *   edge case that would otherwise produce an empty key;
 * - every surviving `*.php` child is delegated to the file loader, so the
 *   injected allowlist is the directory's declared inventory: a child whose
 *   basename is not allowed fails closed instead of silently vanishing;
 * - nested directories are not traversed: an entry named `*.php` that is not
 *   a regular file is delegated to the file loader, which rejects it -- fail
 *   closed instead of silently skipping a source that looks like one;
 * - entries are emitted sorted by key, so the load order is the same on every
 *   filesystem regardless of the directory's internal ordering.
 */
final readonly class DirectoryLoader implements ConfigLoaderInterface
{
    public function __construct(
        private ConfigLoaderInterface $configLoader,
    ) {
    }

    #[Override]
    public function load(string $path): array
    {
        if (!is_dir($path)) {
            throw new ConfigException(
                \sprintf('The configuration source "%s" is not a directory.', $path),
            );
        }

        $config = [];

        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $file) {
            if (!($file instanceof SplFileInfo) || $file->getExtension() !== 'php') {
                continue;
            }

            $name = $file->getFilename();

            if (str_starts_with($name, '.')) {
                continue;
            }

            $config[basename($name, '.php')] = $this->configLoader->load($file->getPathname());
        }

        ksort($config, \SORT_STRING);

        return $config;
    }
}
