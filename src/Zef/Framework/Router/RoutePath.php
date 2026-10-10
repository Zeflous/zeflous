<?php

declare(strict_types=1);

namespace Zef\Framework\Router;

/**
 * A parsed route path: the slash-separated segments of a URL pattern.
 *
 * This is the grammar half of the radix tree engine's front door (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree" -- "Normalize
 * Path": `/api/v1/users/{id}` becomes `[api, v1, users, {id}]`). Parsing is
 * pure: no I/O, no tree, just the path's own characters, so every rule the
 * engine later relies on is decided here, once, fail-closed:
 *
 * - the path is non-empty and starts with `/`;
 * - `/` itself is the root route (zero segments);
 * - the path does not end with `/` and contains no empty segment, so one
 *   canonical spelling exists per shape and trailing-slash questions never
 *   reach the tree;
 * - braces nest at most one level and never span a slash: a parameter
 *   occupies one whole segment, so its boundary and a slash boundary always
 *   coincide and a captured value stays decodable -- `/users/{id}` parses,
 *   `/users{id}`, `/{a}{b}` and `/{a/b}` do not;
 * - a parameter name is `[_A-Za-z][_A-Za-z0-9]*` and appears at most once
 *   per path, so a captured list of values always maps onto distinct names.
 *
 * Validation runs in two passes with one responsibility each: a brace
 * pre-scan over the whole path (the only pass that can see a brace spanning
 * a slash or braces crossing segment boundaries, because the segment split
 * happens after it), then per-segment parsing (whole-parameter form and
 * name rules). Both passes work on whole tokens rather than byte offsets,
 * so a path with multi-byte static text is judged exactly like an ASCII
 * one. Static text is otherwise unconstrained (unicode, dots, dashes,
 * spaces): the engine matches text exactly as written, and path
 * normalisation policy is owned by the HTTP layer, not the engine.
 *
 * {@see self::canonical()} renders the parameter-blind form (every
 * parameter becomes `{}`) that duplicate-route detection compares: it keeps
 * "same shape" a property of the pattern itself instead of a walk over the
 * tree.
 *
 * @api
 */
final readonly class RoutePath
{
    /**
     * @param list<PathSegment> $segments
     */
    private function __construct(private array $segments)
    {
    }

    /**
     * Parses a route path pattern into its segments.
     *
     * Every malformed shape raises a {@see RouterException} that names the
     * path (and, where it narrows the cause, the offending segment or
     * parameter name); nothing is silently repaired, defaulted or dropped.
     *
     * @param string $path the URL pattern, e.g. `/users/{id}`
     */
    public static function parse(string $path): self
    {
        if ($path === '') {
            throw new RouterException('The route path "" is empty.');
        }

        if (!str_starts_with($path, '/')) {
            throw new RouterException(
                \sprintf('The route path "%s" must start with a slash.', $path),
            );
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            throw new RouterException(
                \sprintf('The route path "%s" must not end with a slash.', $path),
            );
        }

        if ($path === '/') {
            return new self([]);
        }

        self::assertBracesAreWellFormed($path);

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

        return new self($segments);
    }

    /**
     * The parsed segments, in path order.
     *
     * @return list<PathSegment>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Whether any segment is a `{name}` parameter.
     */
    public function hasParameters(): bool
    {
        return array_any($this->segments, static fn (PathSegment $pathSegment): bool => $pathSegment->isParameter());
    }

    /**
     * The parameter names, in path order (unique by the grammar).
     *
     * @return list<string>
     */
    public function parameterNames(): array
    {
        $names = [];

        foreach ($this->segments as $segment) {
            if (!$segment->isParameter()) {
                continue;
            }

            $names[] = $segment->value();
        }

        return $names;
    }

    /**
     * The parameter-blind spelling: every parameter segment renders as `{}`.
     *
     * `/users/{id}` and `/users/{name}` share the canonical form
     * `/users/{}`, which is what makes duplicate-route detection a string
     * comparison: two routes may not own the same shape, whatever they call
     * their parameters.
     */
    public function canonical(): string
    {
        $canonical = '';

        foreach ($this->segments as $segment) {
            $canonical .= '/' . ($segment->isParameter() ? '{}' : $segment->value());
        }

        return $canonical === '' ? '/' : $canonical;
    }

    /**
     * Brace pre-scan over the whole path.
     *
     * The path is split on `{`, which makes every part after the first begin
     * inside a brace; splitting such a part on `}` (limit 2) then yields the
     * parameter content before the closing brace and the text after it. The
     * four failure shapes fall out directly: a `}` before any `{` (or after
     * a closed parameter), a part with no `}` at all (a second `{` opened
     * inside -- nested -- unless it is the last part, in which case the
     * brace never closes), and a `/` inside the parameter content (a brace
     * spanning more than one segment). This is the only pass that can see
     * braces crossing segment boundaries, because the segment split happens
     * after it; afterwards every segment's braces are balanced, so the
     * per-segment pass only has to judge *where* inside the segment the
     * braces sit.
     */
    private static function assertBracesAreWellFormed(string $path): void
    {
        $parts = explode('{', $path);

        if (str_contains($parts[0], '}')) {
            throw new RouterException(
                \sprintf('The route path "%s" has an unmatched closing brace.', $path),
            );
        }

        $lastIndex = \count($parts) - 1;

        for ($index = 1; $index <= $lastIndex; ++$index) {
            $halves = explode('}', $parts[$index], 2);

            if (!isset($halves[1])) {
                throw new RouterException(
                    \sprintf(
                        'The route path "%s" has %s parameter brace.',
                        $path,
                        $index === $lastIndex ? 'an unclosed' : 'a nested',
                    ),
                );
            }

            if (str_contains($halves[0], '/')) {
                throw new RouterException(
                    \sprintf('The route path "%s" has a parameter brace that spans more than one segment.', $path),
                );
            }

            if (str_contains($halves[1], '}')) {
                throw new RouterException(
                    \sprintf('The route path "%s" has an unmatched closing brace.', $path),
                );
            }
        }
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

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
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
