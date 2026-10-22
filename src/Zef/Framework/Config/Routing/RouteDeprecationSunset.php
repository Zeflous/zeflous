<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The deprecation declaration's sunset judgement: one question -- which
 * instant does the declared sunset name, and does it retire after the
 * deprecation it belongs to?
 *
 * `sunset` must name an instant strictly after the deprecation date: a
 * resource that retires before (or exactly when) it is deprecated is a
 * retirement, not a deprecation -- the refusal is the declaration-line
 * judgement, not a runtime surprise. The sunset member's own date grammar
 * is {@see RouteDeprecationDate}'s to judge.
 *
 * @internal
 */
final class RouteDeprecationSunset
{
    /**
     * The declared sunset member's instant, refused when it names an
     * instant at or before the deprecation date.
     *
     * @param non-empty-string $value the declared sunset member
     * @param int              $date  the deprecation instant the sunset must follow
     *
     * @return int the sunset instant
     *
     * @throws ConfigException when the grammar is refused or the ordering is inverted
     */
    public static function instant(string $value, int $date, RoutePath $routePath): int
    {
        $sunset = RouteDeprecationDate::instant($value, 'sunset', $routePath);

        if ($sunset <= $date) {
            throw new ConfigException(\sprintf(
                'The route "%s" declares a sunset at or before its deprecation date.',
                $routePath->pattern(),
            ));
        }

        return $sunset;
    }
}
