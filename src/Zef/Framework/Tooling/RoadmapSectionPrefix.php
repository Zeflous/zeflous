<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Resolves an area name to the numbered section it names by prefix.
 *
 * The cross-section contradiction rule ({@see RoadmapContradictionRules}) is the
 * only consumer. It lives in its own class for the same reason the rule classes
 * do: the repository's architecture gate (`php-codearch-config.yaml`,
 * `tooComplex.difficulty: 20`) scores a class that carries several operator
 * varieties above the ceiling, so each file owns one focused review surface.
 *
 * A section matches when its normalised title is a prefix of the normalised area
 * name, so "HTTP Layer" matches the bullet's "HTTP Layer (Zero-Library Shim)".
 */
final class RoadmapSectionPrefix
{
    /**
     * The normalised title of the numbered section the area names, or null when
     * no section matches.
     *
     * @param array<string, int> $sectionRows
     */
    public static function matching(string $area, array $sectionRows): ?string
    {
        $normalised = RoadmapTableCells::normalise($area);

        foreach (array_keys($sectionRows) as $section) {
            if ($section !== '' && str_starts_with($normalised, $section)) {
                return $section;
            }
        }

        return null;
    }
}
