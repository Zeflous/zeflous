<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The OpenAPI paths object: the whole declared table grouped by path
 * pattern -- one Path Item per pattern, one Operation per method the
 * table claims on it.
 *
 * The grouping trusts the engine's own invariants: patterns arrive in
 * the tree's declaration order ({@see \Zef\Framework\Config\Routing\RadixTree::routes()}),
 * methods are the entry's own sorted list, and two entries that share
 * a pattern carry disjoint method claims (the compiler's
 * {@see \Zef\Framework\Config\Routing\RouteMethodClaims} rejects a
 * method claimed twice), so the fold merges without a collision
 * judgement of its own -- the one guarantee the engine already makes.
 *
 * Parameters stay at the OPERATION level, deliberately: the
 * constraints belong to the route entry, and two entries that share a
 * pattern may constrain the same parameter differently (the engine
 * judges them per entry at the leaf), so a path-level parameter
 * declaration would average two truths into one the table never
 * declared.
 *
 * @internal consumed through {@see OpenApiDocument}
 */
final readonly class OpenApiPaths
{
    /**
     * The paths object for the whole declared table.
     *
     * @param list<CompiledRoute> $routes the tree's routes, in declaration order
     *
     * @return array<string, array<string, mixed>> pattern => method (lower-cased) => Operation Object
     *
     * @throws ExportException when any route's enrichment declaration refuses
     */
    public static function grouped(array $routes): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $pattern = $route->path()->pattern();
            $paths[$pattern] ??= [];

            foreach ($route->methods() as $method) {
                $paths[$pattern][mb_strtolower($method)] = OpenApiOperation::fromRoute($route);
            }
        }

        return $paths;
    }
}
