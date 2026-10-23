<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * The tree's node structure as plain rows: the fold behind {@see
 * RadixTree::structure()} -- the debugging seam the CLI's tree dump
 * renders. One question: what does the compiled graph look like?
 *
 * The answer is a flat, depth-annotated row list (one row per node,
 * depth-first: the node's own line, its static children in the
 * builder's insertion order, the parameter edge last -- the same
 * order {@see RouteTreeCacheHydration} replays) rather than a nested
 * shape: a flat list spells the walk as data, carries the depth the
 * indentation needs, and stays a plain list all the way down -- a
 * description of the compiled truth, never a re-derivation from raw
 * entries, and never a cache: nothing rehydrates from it.
 *
 * @internal
 */
final class RouteTreeStructure
{
    private const string EDGE_ROOT = 'root';

    private const string EDGE_STATIC = 'static';

    private const string EDGE_PARAMETER = 'parameter';

    /**
     * Describes the node graph under one root as depth-first rows.
     *
     * @return list<array{depth: int, edge: string, prefix: string, routes: list<string>}>
     */
    public static function rows(RadixNode $radixNode): array
    {
        $rows = [];
        self::walk($radixNode, 0, self::EDGE_ROOT, $rows);

        return $rows;
    }

    /**
     * @param list<array{depth: int, edge: string, prefix: string, routes: list<string>}> $rows
     */
    private static function walk(RadixNode $radixNode, int $depth, string $edge, array &$rows): void
    {
        $patterns = [];

        foreach ($radixNode->routes() as $compiledRoute) {
            $patterns[] = $compiledRoute->path()->pattern();
        }

        $rows[] = [
            'depth' => $depth,
            'edge' => $edge,
            'prefix' => $radixNode->prefix(),
            'routes' => $patterns,
        ];

        foreach ($radixNode->staticChildren() as $staticChild) {
            self::walk($staticChild, $depth + 1, self::EDGE_STATIC, $rows);
        }

        $parameterChild = $radixNode->parameterChild();

        if (!$parameterChild instanceof RadixNode) {
            return;
        }

        self::walk($parameterChild, $depth + 1, self::EDGE_PARAMETER, $rows);
    }
}
