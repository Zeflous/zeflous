<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The declaration-building side of the route DSL's deprecation verb: one
 * question -- what map does a `deprecated()` call freeze into the route's
 * schema entry?
 *
 * Mirrors {@see RouteDslConstraints::validated()} (the `where` verb's
 * own fold): the member values are checked non-blank on the declaration
 * line -- a member that is empty or all whitespace is a defect there, not
 * a default to silently repair -- and only the declared members ride the
 * frozen map. The declare-once bookkeeping stays with
 * {@see RouteDslFields}; the members' grammar (the date forms, the
 * sunset-after-deprecation ordering, the link's renderable bytes) is
 * judged by {@see RouteDeprecation} at compile time, exactly like a
 * `where` pattern's own compilation is the engine's judgement.
 *
 * @internal
 */
final class RouteDslDeprecation
{
    /**
     * Builds the deprecation declaration map from the verb's arguments.
     *
     * @param string      $date   the declared deprecation date
     * @param null|string $sunset the declared sunset date, when given
     * @param null|string $link   the declared documentation link, when given
     *
     * @return array{date: non-empty-string, sunset?: non-empty-string, link?: non-empty-string}
     *
     * @throws ConfigException when a declared member is blank
     */
    public static function declared(string $date, ?string $sunset, ?string $link, RoutePath $routePath): array
    {
        $declaration = ['date' => self::nonBlank($date, 'date', $routePath)];

        if ($sunset !== null) {
            $declaration['sunset'] = self::nonBlank($sunset, 'sunset', $routePath);
        }

        if ($link !== null) {
            $declaration['link'] = self::nonBlank($link, 'link', $routePath);
        }

        return $declaration;
    }

    /**
     * One deprecation member's non-blank value.
     *
     * @return non-empty-string
     */
    private static function nonBlank(string $value, string $member, RoutePath $routePath): string
    {
        $trimmed = mb_trim($value);

        if ($trimmed === '') {
            throw new ConfigException(\sprintf(
                'The route "%s" must not declare a blank deprecation "%s".',
                $routePath->pattern(),
                $member,
            ));
        }

        return $trimmed;
    }
}
