<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The Accept header's media ranges, parsed once into the shape negotiation
 * needs ({@see RouteAcceptRange}): type, subtype, quality and specificity per
 * range. Unparsable ranges and invalid quality values are skipped -- an
 * untrusted header is runtime data, not a defect.
 *
 * @internal
 */
final class RouteAcceptRanges
{
    /**
     * @return list<RouteAcceptRange>
     */
    public static function parse(string $accept): array
    {
        $ranges = [];

        foreach (explode(',', $accept) as $entry) {
            $parts = array_map(trim(...), explode(';', $entry));
            $mediaRange = mb_strtolower(array_shift($parts));
            if (preg_match('/\A(\*|[a-z0-9!#$&^_.+-]+)\/(\*|[a-z0-9!#$&^_.+-]+)\z/D', $mediaRange, $matches) !== 1) {
                continue;
            }

            if ($matches[1] === '*' && $matches[2] !== '*') {
                continue;
            }

            $quality = self::quality($parts);
            if ($quality === null) {
                continue;
            }

            $ranges[] = new RouteAcceptRange(
                $matches[1],
                $matches[2],
                $quality,
                ($matches[1] === '*' ? 0 : 1) + ($matches[2] === '*' ? 0 : 1),
            );
        }

        return $ranges;
    }

    /**
     * The most specific matching range controls quality, so q=0 cannot be
     * overridden by a less-specific wildcard; an invalid quality marks the
     * whole range invalid (skipped by the caller), and the last valid
     * quality wins as the header grammar's own repetition rule.
     *
     * @param list<string> $parts the range's remaining parameters
     */
    private static function quality(array $parts): ?float
    {
        $quality = 1.0;

        foreach ($parts as $parameter) {
            if (!str_starts_with(mb_strtolower($parameter), 'q=')) {
                continue;
            }

            $rawQuality = mb_substr($parameter, 2);
            if (preg_match('/\A(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)\z/D', $rawQuality) !== 1) {
                return null;
            }

            $quality = (float) $rawQuality;
        }

        return $quality;
    }
}
