<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Router\Router;

/**
 * The route list's JSON form: the tool-integration view the inspection
 * CLI answers with `--json` -- one record per declared route ({@see
 * RouteListJsonRecord}, the stable record shape the route test's
 * found answer shares), in the tree's declaration order ({@see
 * \Zef\Framework\Config\Routing\RadixTree::routes()}), closed by the
 * table's own size.
 */
final readonly class RouteListJson
{
    /**
     * The whole declared table as a JSON document.
     */
    public static function rendered(Router $router): string
    {
        $records = [];

        foreach ($router->tree()->routes() as $compiledRoute) {
            $records[] = RouteListJsonRecord::record($compiledRoute);
        }

        return RouteInspectionJson::encode(['routes' => $records, 'count' => \count($records)]);
    }
}
