<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * A parsed route path: the slash-separated segments of a URL pattern.
 *
 * This is the value half of the radix tree engine's front door (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree" -- "Normalize
 * Path": `/api/v1/users/{id}` becomes `[api, v1, users, {id}]`). The
 * grammar itself lives in {@see RoutePathGrammar}; {@see self::parse()}
 * owns only the path-level shape rules and hands the rest over:
 *
 * - the path is non-empty and starts with `/`;
 * - `/` itself is the root route (zero segments);
 * - the path does not end with `/`, so one canonical spelling exists per
 *   shape and trailing-slash questions never reach the tree;
 * - below the first slash, every segment is either static text or one
 *   whole-segment `{name}` parameter, braces never span a slash, and a
 *   parameter name appears at most once per path.
 *
 * Static text is otherwise unconstrained (unicode, dots, dashes, spaces):
 * the engine matches text exactly as written, and path normalisation
 * policy is owned by the HTTP layer, not the engine.
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
     * The root route pattern: the one path whose segment list is empty.
     */
    private const string ROOT_PATH = '/';

    /**
     * @param list<PathSegment> $segments
     */
    private function __construct(private array $segments)
    {
    }

    /**
     * Parses a route path pattern into its segments.
     *
     * Every malformed shape raises a {@see ConfigException} that names the
     * path (and, where it narrows the cause, the offending segment or
     * parameter name); nothing is silently repaired, defaulted or dropped.
     *
     * @param string $path the URL pattern, e.g. `/users/{id}`
     */
    public static function parse(string $path): self
    {
        if ($path === '') {
            throw new ConfigException('The route path "" is empty.');
        }

        if (!str_starts_with($path, '/')) {
            throw new ConfigException(
                \sprintf('The route path "%s" must start with a slash.', $path),
            );
        }

        if ($path === self::ROOT_PATH) {
            return new self([]);
        }

        if (str_ends_with($path, '/')) {
            throw new ConfigException(
                \sprintf('The route path "%s" must not end with a slash.', $path),
            );
        }

        return new self(RoutePathGrammar::segments($path));
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
        $canonicalForm = '';

        foreach ($this->segments as $segment) {
            $canonicalForm .= '/' . $segment->canonical();
        }

        if ($canonicalForm === '') {
            return self::ROOT_PATH;
        }

        return $canonicalForm;
    }
}
