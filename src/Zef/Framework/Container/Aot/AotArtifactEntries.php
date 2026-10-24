<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact's entries walk: the per-definition half of the boundary's
 * strictest surface -- every payload entry handed to the reader that
 * validates its own kind ({@see AotArtifactEntry}), the map keyed by
 * the identifiers the walk guards.
 *
 * The refusals stay {@see AotRefusals'} alone, the file named in every
 * envelope-level one.
 */
final readonly class AotArtifactEntries
{
    /**
     * Validates the payload's entries member into its typed map.
     *
     * @param mixed $entries the payload's `entries` member
     *
     * @return array<string, AotBuildEntry|AotClosureEntry>
     */
    public static function validated(string $file, mixed $entries): array
    {
        if (!\is_array($entries)) {
            throw AotRefusals::artifactShape($file, 'the entries must be a map');
        }

        $validated = [];

        foreach (array_keys($entries) as $id) {
            if (!\is_string($id) || $id === '') {
                throw AotRefusals::entryMalformed($id);
            }

            $validated[$id] = AotArtifactEntry::validated($id, $entries[$id]);
        }

        return $validated;
    }
}
