<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Router\Router;

/**
 * The route list's table form: the plain listing the inspection CLI
 * answers -- one line per declared route ({@see RouteListLine}, the
 * entry's own data), headed by the table's own size, in the tree's
 * declaration order ({@see \Zef\Framework\Config\Routing\RadixTree::routes()}),
 * so a host reads its route table exactly as it spelled it.
 *
 * The zero-routing table is valid and answered honestly: the header
 * says zero routes, no line follows, and the answer still exits 0 --
 * the configuration system's own doctrine that a route-less
 * configuration boots.
 */
final readonly class RouteListText
{
    /**
     * The whole declared table, one line per route.
     */
    public static function table(Router $router): string
    {
        $routes = $router->tree()->routes();

        $lines = [
            \sprintf('Route list -- %d routes', \count($routes)),
        ];

        foreach ($routes as $route) {
            $lines[] = '  ' . RouteListLine::rendered($route);
        }

        return implode("\n", $lines) . "\n";
    }
}
