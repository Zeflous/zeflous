<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * One artifact entry's reader: the kind dispatch that hands the payload
 * entry to the value object validating its own shape
 * ({@see AotClosureEntry::validated()}, {@see AotBuildEntry::validated()}),
 * with the lifetime conversion every kind shares -- one spelling,
 * converted once, passed into the shape that chose it.
 *
 * The dispatch refuses what no kind carries: an unknown string spelling
 * ({@see AotRefusals::entryUnknownKind()}) and anything else
 * ({@see AotRefusals::entryMalformed()}) -- before any member is read,
 * the same first-things-first order the envelope's own guards keep.
 */
final readonly class AotArtifactEntry
{
    /**
     * Reads one payload entry into the value object its kind carries:
     * the kind dispatch first, the lifetime every kind shares read once
     * per arm, and what no kind carries refused before any member is
     * read -- the same first-things-first order the envelope's own
     * guards keep.
     *
     * @param mixed $entry the entry the payload carried
     */
    public static function validated(string $id, mixed $entry): AotBuildEntry|AotClosureEntry
    {
        if (!\is_array($entry)) {
            throw AotRefusals::entryMalformed($id);
        }

        return match ($entry['kind'] ?? null) {
            'closure' => AotClosureEntry::validated($id, $entry, AotLifetimes::from($id, $entry)),
            'build' => AotBuildEntry::validated($id, $entry, AotLifetimes::from($id, $entry)),
            default => throw AotRefusals::entryKindRefused($id, $entry['kind'] ?? null),
        };
    }
}
