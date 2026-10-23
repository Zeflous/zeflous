<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Router\Router;

/**
 * The route tree command's text form: the operator's view of the
 * compiled graph itself ({@see \Zef\Framework\Config\Routing\RadixTree::structure()},
 * the engine's own depth-first rows -- never a re-derivation) -- one
 * node per line, indented by the row's own depth: the node's run,
 * the canonical patterns of the routes settled on the node.
 *
 * An empty run spells what the node is instead of the text it has
 * not got: the tree's bare root renders `(root)`, and a parameter
 * edge -- whose run the builder always owns empty, the trailing
 * text after a parameter settling as the edge's own child row --
 * renders `{parameter}`. A pattern-less interior node renders as
 * the bare run it is: the shared prefix the compression bought is
 * exactly what the dump exists to show.
 */
final readonly class RouteTreeText
{
    private const string INDENT = '  ';

    /**
     * The compiled graph, spelled for the terminal.
     */
    public static function rendered(Router $router): string
    {
        $lines = ['Route tree -- the compiled graph'];

        foreach ($router->tree()->structure() as $row) {
            $lines[] = str_repeat(self::INDENT, $row['depth']) . self::label($row);

            $routeIndent = str_repeat(self::INDENT, $row['depth'] + 1);

            foreach ($row['routes'] as $pattern) {
                $lines[] = $routeIndent . 'route ' . $pattern;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * One row's own label: the run it carries, or the marker its
     * empty run spells.
     *
     * @param array{depth: int, edge: string, prefix: string, routes: list<string>} $row
     */
    private static function label(array $row): string
    {
        if ($row['prefix'] === '') {
            return $row['edge'] === 'parameter' ? '{parameter}' : '(root)';
        }

        return $row['prefix'];
    }
}
