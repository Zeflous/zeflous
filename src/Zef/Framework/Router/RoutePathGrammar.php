<?php

declare(strict_types=1);

namespace Zef\Framework\Router;

/**
 * The grammar mechanics below the first slash of a route path.
 *
 * The split keeps each class one thing: {@see RoutePath} is the readonly
 * value the engine holds and owns the path-level shape rules (non-empty,
 * leading `/`, root, no trailing `/`); {@see PathBraceScanner} asserts the
 * path's brace structure; this class splits the already-validated path
 * into segments and judges each one -- static text, or exactly one
 * whole-segment `{name}` parameter with a `[_A-Za-z]\w*` name that no
 * other segment of the same path already uses.
 *
 * Both collaborators work on whole tokens (`explode`, `str_contains`,
 * `preg_match`) rather than byte offsets, so a path with multi-byte static
 * text is judged exactly like an ASCII one. Every failure raises a
 * {@see RouterException} that names the path (and, where it narrows the
 * cause, the offending segment or parameter name); nothing is silently
 * repaired, defaulted or dropped.
 *
 * @internal
 */
final class RoutePathGrammar
{
    /**
     * Parses a path with an already validated shape and brace structure
     * into its segments.
     *
     * @return list<PathSegment>
     */
    public static function segments(string $path): array
    {
        PathBraceScanner::assertWellFormed($path);

        $segments = [];
        $names = [];

        foreach (\array_slice(explode('/', $path), 1) as $segment) {
            if ($segment === '') {
                throw new RouterException(
                    \sprintf('The route path "%s" must not contain an empty segment.', $path),
                );
            }

            $segments[] = self::parseSegment($path, $segment, $names);
        }

        return $segments;
    }

    /**
     * Parses one segment: static text, or one whole-segment parameter.
     *
     * The brace pre-scan has already balanced the path's braces, so a
     * segment without an opening brace is pure static text, and a segment
     * with one must be exactly a single brace pair wrapping the whole
     * segment -- `{a}`, not `a{b}c`, `{a}b` or `{a}{b}`.
     *
     * @param array<string, string> $names parameter names already seen on this path
     */
    private static function parseSegment(string $path, string $segment, array &$names): PathSegment
    {
        if (!str_contains($segment, '{')) {
            return PathSegment::static($segment);
        }

        if (preg_match('/^\{([^{}]*)\}$/', $segment, $match) !== 1) {
            throw new RouterException(
                \sprintf(
                    'The route path "%s" has a segment "%s" whose braces do not form a whole parameter.',
                    $path,
                    $segment,
                ),
            );
        }

        $name = $match[1];

        if ($name === '') {
            throw new RouterException(
                \sprintf('The route path "%s" has an empty parameter name.', $path),
            );
        }

        if (preg_match('/^[_A-Za-z]\w*$/', $name) !== 1) {
            throw new RouterException(
                \sprintf('The route path "%s" has an invalid parameter name "%s".', $path, $name),
            );
        }

        if (isset($names[$name])) {
            throw new RouterException(
                \sprintf('The route path "%s" repeats the parameter name "%s".', $path, $name),
            );
        }

        $names[$name] = $name;

        return PathSegment::parameter($name);
    }
}
