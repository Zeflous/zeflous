<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Reads the numbered section heading of a roadmap document line.
 *
 * It lives in its own class for the same reason the other roadmap rules do:
 * the repository's architecture gate (`php-codearch-config.yaml`,
 * `tooComplex.difficulty: 20`) scores a class that carries several operator
 * varieties above the ceiling, so the heading grammar is split out of
 * {@see RoadmapSectionRows} and each file stays under it.
 */
final class RoadmapSectionHeading
{
    /**
     * The normalised title of a numbered section heading (`## 1. Container`),
     * or null when the line is not a numbered heading.
     */
    public static function title(string $line): ?string
    {
        if (preg_match('/^## \d+\.\s+(.+)/', $line, $matches) !== 1) {
            return null;
        }

        return RoadmapTableCells::normalise($matches[1]);
    }
}
