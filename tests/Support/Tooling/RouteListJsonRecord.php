<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The JSON views' route record: the one stable per-route shape the
 * route list ({@see RouteListJson}) and the route test's found answer
 * ({@see RouteMatchJson}) both render -- every Standard Route Schema
 * field present, the absent optionals spelled as their own empty
 * answers (`null` and `[]`) so a consumer's schema is stable across
 * routes, the deprecation instants rendered as RFC 3339 UTC (the
 * lossless spelling -- the text line renders the scannable day
 * granularity).
 */
final readonly class RouteListJsonRecord
{
    /**
     * @return array<string, mixed> one route's stable record shape
     */
    public static function record(CompiledRoute $compiledRoute): array
    {
        $deprecation = $compiledRoute->deprecation();

        return [
            'name' => $compiledRoute->name(),
            'methods' => $compiledRoute->methods(),
            'path' => $compiledRoute->path()->pattern(),
            'host' => $compiledRoute->host(),
            'version' => $compiledRoute->version(),
            'produces' => $compiledRoute->produces(),
            'middleware' => $compiledRoute->middleware(),
            'handler' => $compiledRoute->handler(),
            'deprecation' => $deprecation === null ? null : [
                'date' => gmdate('Y-m-d\TH:i:s\Z', $deprecation['date']),
                'sunset' => $deprecation['sunset'] === null
                    ? null
                    : gmdate('Y-m-d\TH:i:s\Z', $deprecation['sunset']),
                'link' => $deprecation['link'],
            ],
        ];
    }
}
