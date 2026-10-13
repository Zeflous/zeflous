<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Resolves a `Summary` area label to the numbered section it names.
 *
 * The count-consistency rule ({@see RoadmapCountRules}) is the only consumer.
 * A section matches when its normalised title starts with the label's first
 * word, so "Roadmap docs" matches "Roadmap documentation" and "CI / Supply
 * chain" matches "CI / Supply chain". A label that matches zero or several
 * sections resolves to null, so the mapping can never be silently ambiguous.
 */
final class RoadmapSectionLookup
{
    /**
     * The data-row count of the section a label names, or null when no single
     * section matches.
     *
     * @param array<string, int> $sectionRows
     */
    public static function rowsFor(string $label, array $sectionRows): ?int
    {
        $firstWord = RoadmapTableCells::firstWord($label);

        if ($firstWord === '') {
            return null;
        }

        $matches = [];

        foreach ($sectionRows as $section => $rows) {
            if (!str_starts_with($section, $firstWord)) {
                continue;
            }

            $matches[] = $rows;
        }

        return \count($matches) === 1 ? $matches[0] : null;
    }
}
