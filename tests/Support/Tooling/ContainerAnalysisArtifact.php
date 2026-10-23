<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The analysis boots' throwaway artifact seam: the one file path the
 * analysis CLIs own ({@see ContainerInventoryReport} and {@see
 * RouteInspectionBoot}). The host's real boot wires their own
 * container AOT cache location, which a CLI cannot know -- this unit
 * allocates the throwaway spelling instead: under the system's
 * temporary directory, the `zef-container-inventory-` prefix, the
 * sixteen hexadecimal characters {@see random_bytes()} answers, and
 * the `.php` extension the artifact dump writes.
 *
 * The path is never pre-created: an absent artifact is the honest
 * first boot (the full lane compiles the same declarations the host's
 * own boot would have answered from), and a file that exists at this
 * path is one the report's own dump wrote and its own finally
 * removed. The entropy is `random_bytes` (not a clock-derived
 * `uniqid`): a throwaway name a co-tenant of the temporary directory
 * could predict is a name they can pre-create ahead of the dump, and
 * unpredictability is the one property a throwaway path needs.
 */
final readonly class ContainerAnalysisArtifact
{
    private const string PREFIX = 'zef-container-inventory-';

    private const string EXTENSION = '.php';

    private const int ENTROPY_BYTES = 8;

    /**
     * One throwaway artifact path, unique per allocation: the prefix,
     * the sixteen hexadecimal characters random_bytes answers, the
     * extension.
     *
     * @return non-empty-string the artifact file path, never created
     */
    public static function path(): string
    {
        return sys_get_temp_dir() . \DIRECTORY_SEPARATOR . self::PREFIX
            . bin2hex(random_bytes(self::ENTROPY_BYTES)) . self::EXTENSION;
    }
}
