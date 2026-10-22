<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Canonicalises one route's deprecation declaration -- the RFC 9745
 * `Deprecation` date, the RFC 8594 `Sunset` date and the deprecation
 * documentation link a deprecated route answers with on every response
 * (`ROADMAP.md`, "API Versioning": the deprecation middleware's declaration
 * half, declared per route and rendered by
 * {@see \Zef\Framework\Middleware\RouteDeprecationHeaders}).
 *
 * The members' own grammars live with their folds: the date shapes and the
 * rolled-over refusal with {@see RouteDeprecationDate}, the link's
 * renderable bytes with {@see RouteDeprecationLink}. What this fold owns
 * is the declaration's assembly contract: `sunset` must name an instant
 * strictly after the deprecation date -- a resource that retires before
 * (or exactly when) it is deprecated is a retirement, not a deprecation
 * -- and the member vocabulary the shape side
 * ({@see RouteEntryDeprecation}) refuses unknown keys against.
 *
 * @internal
 */
final class RouteDeprecation
{
    /**
     * The declaration members a deprecation may carry: the required
     * `date`, and the optional `sunset` and `link`.
     */
    private const array MEMBERS = [
        'date' => true,
        'sunset' => true,
        'link' => true,
    ];

    /**
     * Canonicalises a shape-checked deprecation declaration into the
     * compiled field the dispatch lane renders from.
     *
     * @param array{
     *     date: non-empty-string,
     *     sunset?: non-empty-string,
     *     link?: non-empty-string,
     * } $declaration the shape-checked declaration
     * @param RoutePath $routePath the route the declaration belongs to, for the failure message
     *
     * @return array{date: int, sunset: null|int, link: null|non-empty-string}
     *
     * @throws ConfigException when a member's grammar is refused
     */
    public static function canonical(array $declaration, RoutePath $routePath): array
    {
        $date = RouteDeprecationDate::instant($declaration['date'], 'date', $routePath);

        $sunset = null;
        if (\array_key_exists('sunset', $declaration)) {
            $sunset = RouteDeprecationSunset::instant($declaration['sunset'], $date, $routePath);
        }

        $link = null;
        if (\array_key_exists('link', $declaration)) {
            $link = RouteDeprecationLink::validated($declaration['link'], $routePath);
        }

        return ['date' => $date, 'sunset' => $sunset, 'link' => $link];
    }

    /**
     * The members a deprecation declaration may carry, keyed by name; the
     * shape side ({@see RouteEntryDeprecation}) refuses any other key
     * against this same vocabulary.
     *
     * @return array<non-empty-string, true>
     */
    public static function members(): array
    {
        return self::MEMBERS;
    }
}
