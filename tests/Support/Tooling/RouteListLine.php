<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The route list's line form: the compact per-route view the table
 * listing renders -- the name (or the honest `(unnamed)` spelling),
 * the canonical method set, the pattern with its host claim spelled
 * the explanation family's own way (`pattern @host`), the handler,
 * and the optional declarations a route carries (middleware, version,
 * producible media types, the deprecation lifecycle) each in its own
 * tagged tail so a line stays scannable at any table size.
 *
 * Pure presentation over the compiled entry's own data, kept its own
 * archeology-sized unit beside the table and JSON forms ({@see
 * RouteListText}, {@see RouteListJson}): every field read straight
 * off the entry, never re-derived.
 */
final readonly class RouteListLine
{
    /**
     * One compiled route's table line.
     */
    public static function rendered(CompiledRoute $compiledRoute): string
    {
        $pattern = $compiledRoute->path()->pattern();

        if ($compiledRoute->host() !== null) {
            $pattern .= ' @' . $compiledRoute->host();
        }

        $parts = [
            $compiledRoute->name() ?? '(unnamed)',
            implode(',', $compiledRoute->methods()),
            $pattern,
            $compiledRoute->handler(),
        ];

        if ($compiledRoute->version() !== null) {
            $parts[] = 'version: ' . $compiledRoute->version();
        }

        if ($compiledRoute->produces() !== []) {
            $parts[] = 'produces: ' . implode(',', $compiledRoute->produces());
        }

        if ($compiledRoute->middleware() !== []) {
            $parts[] = 'middleware: ' . implode(',', $compiledRoute->middleware());
        }

        $deprecation = $compiledRoute->deprecation();

        if ($deprecation !== null) {
            $parts[] = self::deprecation($deprecation);
        }

        return implode('  ', $parts);
    }

    /**
     * The deprecation tail: the RFC 9745 instant and the RFC 8594
     * sunset spelled at day granularity (the scannable form -- the
     * full instants are the headers the route answers at runtime),
     * and the documentation link when the declaration carries one.
     *
     * @param array{date: int, sunset: null|int, link: null|non-empty-string} $deprecation
     */
    private static function deprecation(array $deprecation): string
    {
        $tail = 'deprecated: ' . gmdate('Y-m-d', $deprecation['date']);

        if ($deprecation['sunset'] !== null) {
            $tail .= ', sunset: ' . gmdate('Y-m-d', $deprecation['sunset']);
        }

        if ($deprecation['link'] !== null) {
            $tail .= ', link: ' . $deprecation['link'];
        }

        return $tail;
    }
}
