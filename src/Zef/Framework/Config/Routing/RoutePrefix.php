<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * A route group prefix: the slash-shaped text a {@see RouteDsl} group
 * contributes to every route declared inside it.
 *
 * A prefix is validated once, on the declaration line: non-empty, starting
 * with a slash, and without a trailing slash (the root prefix `/` alone is
 * legal and means "no prefix"). The shape rules deliberately mirror
 * {@see RoutePath::parse()} -- the grammar's owner -- so a malformed prefix
 * reads like a malformed path, and a prefix may itself carry parameters
 * (`/users/{user}` composes into a pattern the grammar accepts as a whole).
 *
 * {@see self::concat()} joins the prefix with a declared sub-path under the
 * root-prefix semantics the DSL needs: a root prefix leaves the path alone,
 * a root path yields the prefix itself, and nothing ever produces a double
 * slash or a trailing one.
 *
 * @internal
 */
final readonly class RoutePrefix
{
    /**
     * The one prefix that contributes no text: the root route's own slash.
     */
    private const string ROOT_PREFIX = '/';

    private function __construct(private string $prefix)
    {
    }

    /**
     * Validates a declared prefix and returns it as a value object.
     *
     * Every malformed shape raises a {@see ConfigException} naming the
     * offending prefix; nothing is silently repaired or defaulted.
     */
    public static function fromString(string $prefix): self
    {
        if ($prefix === '') {
            throw new ConfigException('The route prefix "" is empty.');
        }

        if (!str_starts_with($prefix, '/')) {
            throw new ConfigException(
                \sprintf('The route prefix "%s" must start with a slash.', $prefix),
            );
        }

        if ($prefix !== self::ROOT_PREFIX && str_ends_with($prefix, '/')) {
            throw new ConfigException(
                \sprintf('The route prefix "%s" must not end with a slash.', $prefix),
            );
        }

        return new self($prefix);
    }

    /**
     * Joins the prefix with a declared sub-path: the composed pattern a
     * route carries once its group prefixes are applied.
     *
     * The path must itself be slash-shaped (non-empty and starting with a
     * slash), so the join can never glue text onto a prefix by accident.
     * A root prefix returns the path verbatim; a root path returns the
     * prefix itself -- the exact rule that keeps `/` inside a group meaning
     * "this group's own route" without ever spelling a trailing slash.
     */
    public function concat(string $path): string
    {
        if ($path === '') {
            throw new ConfigException('The route path "" is empty.');
        }

        if (!str_starts_with($path, '/')) {
            throw new ConfigException(
                \sprintf('The route path "%s" must start with a slash.', $path),
            );
        }

        if ($this->prefix === self::ROOT_PREFIX) {
            return $path;
        }

        if ($path === self::ROOT_PREFIX) {
            return $this->prefix;
        }

        return $this->prefix . $path;
    }
}
