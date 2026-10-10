<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Loader;

use Zef\Framework\Config\ConfigException;

/**
 * Contract of a configuration format adapter.
 *
 * The configuration layer loads its sources through one adapter per format
 * (PHP files first; a future JSON or YAML adapter must be an in-repo shim,
 * never a runtime dependency -- see `CONTRIBUTING.md` rule 1). A loader turns
 * one addressed source into a plain configuration array, so the director and
 * the layered builder stay format-agnostic: adding a format means adding an
 * implementation, not touching any consumer.
 *
 * The contract is the configuration layer's extension seam: host applications
 * may ship their own adapter, which is why it is marked `@api` rather than
 * being baselined or suppressed.
 *
 * @api
 */
interface ConfigLoaderInterface
{
    /**
     * Loads the configuration array addressed by the source path.
     *
     * Loading is fail-closed: a source that cannot be read, or that does not
     * produce an array, raises {@see ConfigException} naming the offending
     * path -- never a silent fallback to an empty configuration.
     *
     * @return array<array-key, mixed>
     *
     * @throws ConfigException when the source cannot be read or does not produce an array
     */
    public function load(string $path): array;
}
