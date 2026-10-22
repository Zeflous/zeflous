<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Picks the newest history row of a roadmap document.
 *
 * The pointer-freshness rule ({@see RoadmapPointerRules}) is the only consumer.
 * "Newest" is the greatest merge date and, among rows sharing that date, the
 * greatest pull-request number.
 */
final class RoadmapNewestRow
{
    /**
     * The newest row, or null when the list is empty.
     *
     * @param list<array{0: string, 1: int, 2: string}> $rows
     *
     * @return null|array{0: string, 1: int, 2: string}
     */
    public static function of(array $rows): ?array
    {
        $newest = null;

        foreach ($rows as $row) {
            if ($newest !== null && !self::isNewer($row, $newest)) {
                continue;
            }

            $newest = $row;
        }

        return $newest;
    }

    /**
     * Whether the candidate row is strictly newer than the current one: a
     * greater merge date, or the same date and a greater pull-request number.
     *
     * @param array{0: string, 1: int, 2: string} $candidate
     * @param array{0: string, 1: int, 2: string} $current
     */
    private static function isNewer(array $candidate, array $current): bool
    {
        return $candidate[0] > $current[0]
            || ($candidate[0] === $current[0] && $candidate[1] > $current[1]);
    }
}
