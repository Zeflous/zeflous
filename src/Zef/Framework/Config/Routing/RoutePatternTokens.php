<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The token stream a route's pattern becomes for tree insertion.
 *
 * A parsed path is flattened into alternating static runs and anonymous
 * parameter tokens: `/users/{id}/posts` becomes the run `/users/` (a run
 * carries the separators around it, so it may span several segments --
 * the radix compression), the parameter `id`, and the run `/posts`. The
 * root pattern `/` becomes the single run `/`. A parameter never carries
 * text into a run and a run never swallows a parameter boundary, so the
 * tree can treat the two token kinds as exactly two edge kinds.
 *
 * @internal
 */
final class RoutePatternTokens
{
    /**
     * The token stream of a parsed path.
     *
     * @return list<array{bool, string}>
     */
    public static function of(RoutePath $routePath): array
    {
        $tokens = [];
        $static = '';

        foreach ($routePath->segments() as $pathSegment) {
            if ($pathSegment->isParameter()) {
                $tokens[] = [false, $static . '/'];
                $tokens[] = [true, $pathSegment->value()];
                $static = '';

                continue;
            }

            $static .= '/' . $pathSegment->value();
        }

        if ($static !== '') {
            $tokens[] = [false, $static];
        }

        if ($tokens === []) {
            return [[false, '/']];
        }

        return $tokens;
    }
}
