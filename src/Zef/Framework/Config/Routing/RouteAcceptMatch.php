<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Whether one media type is permitted by a parsed Accept range list: the
 * most specific matching range's quality decides, so a specific q=0
 * exclusion cannot be overridden by a less-specific wildcard.
 *
 * @internal
 */
final class RouteAcceptMatch
{
    /**
     * @param list<RouteAcceptRange> $ranges
     */
    public static function accepts(array $ranges, string $type, string $subtype): bool
    {
        $bestSpecificity = -1;
        $bestQuality = 0.0;

        foreach ($ranges as $range) {
            if (!$range->matches($type, $subtype)) {
                continue;
            }

            if ($range->specificity > $bestSpecificity) {
                $bestSpecificity = $range->specificity;
                $bestQuality = $range->quality;
            } elseif ($range->specificity === $bestSpecificity) {
                $bestQuality = max($bestQuality, $range->quality);
            }
        }

        // No matching range and a matching q=0 exclusion agree on the answer
        // (both leave the best quality at zero), so the quality alone decides.
        return $bestQuality > 0.0;
    }
}
