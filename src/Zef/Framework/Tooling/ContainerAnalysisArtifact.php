<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The analysis boot's throwaway artifact seam: the one file path the
 * container analysis CLI owns ({@see ContainerInventoryReport}). The
 * host's real boot wires their own container AOT cache location,
 * which the CLI cannot know -- this unit allocates the throwaway
 * spelling instead: under the system's temporary directory, the
 * `zef-container-inventory-` prefix, the thirteen hexadecimal
 * characters {@see uniqid()} answers, and the `.php` extension the
 * artifact dump writes.
 *
 * The path is never pre-created: an absent artifact is the honest
 * first boot (the full lane compiles the same declarations the host's
 * own boot would have answered from), and a file that exists at this
 * path is one the report's own dump wrote and its own finally
 * removed.
 */
final readonly class ContainerAnalysisArtifact
{
    private const string PREFIX = 'zef-container-inventory-';

    private const string EXTENSION = '.php';

    /**
     * One throwaway artifact path, unique per allocation: the prefix,
     * the thirteen hexadecimal characters uniqid answers, the
     * extension.
     *
     * @return non-empty-string the artifact file path, never created
     */
    public static function path(): string
    {
        return sys_get_temp_dir() . \DIRECTORY_SEPARATOR . self::PREFIX
            . uniqid('', false) . self::EXTENSION;
    }
}
