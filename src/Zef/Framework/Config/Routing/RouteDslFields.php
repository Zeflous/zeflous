<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The declare-once optional fields of a pending route: the name, the
 * parameter constraints, and the metadata.
 *
 * Each of these fields is declared at most once -- a second declaration
 * is an ambiguous route, not a merge -- and each declaration is
 * validated against the route's own parsed path on the declaration
 * line: an empty name, empty constraints, a constraint for a parameter
 * the pattern never declares, or metadata that is not a non-empty map
 * all fail closed with the route in the message. The middleware stack
 * is deliberately not here: it is append-only by design and lives in
 * {@see RouteDslMiddleware}.
 *
 * @internal
 */
final class RouteDslFields
{
    private ?string $name = null;

    /** @var null|array<string, string> */
    private ?array $constraints = null;

    /** @var null|array<array-key, mixed> */
    private ?array $meta = null;

    /**
     * Declares the reverse-routing name; a second name is an ambiguous
     * declaration, not a rename.
     */
    public function name(string $name, RoutePath $routePath): void
    {
        if ($name === '') {
            throw new ConfigException(
                \sprintf('The route "%s" must not declare an empty name.', $routePath->pattern()),
            );
        }

        if ($this->name !== null) {
            throw new ConfigException(
                \sprintf(
                    'The route "%s" already declares the name "%s".',
                    $routePath->pattern(),
                    $this->name,
                ),
            );
        }

        $this->name = $name;
    }

    /**
     * Declares the parameter constraints (see {@see RouteDslConstraints}
     * for the checks); a second declaration is an overwrite in disguise.
     *
     * @param array<array-key, mixed> $constraints
     */
    public function where(array $constraints, RoutePath $routePath): void
    {
        if ($constraints === []) {
            throw new ConfigException(
                \sprintf('The route "%s" must not declare empty constraints.', $routePath->pattern()),
            );
        }

        if ($this->constraints !== null) {
            throw new ConfigException(
                \sprintf('The route "%s" already declares its constraints.', $routePath->pattern()),
            );
        }

        $this->constraints = RouteDslConstraints::validated($constraints, $routePath);
    }

    /**
     * Declares the free-form route metadata; a second declaration is an
     * overwrite in disguise, and the values are the metadata map's own
     * business, not the DSL's.
     *
     * @param array<array-key, mixed> $meta
     */
    public function meta(array $meta, RoutePath $routePath): void
    {
        if ($meta === [] || array_is_list($meta)) {
            throw new ConfigException(
                \sprintf('The route "%s" must declare metadata as a non-empty map.', $routePath->pattern()),
            );
        }

        if ($this->meta !== null) {
            throw new ConfigException(
                \sprintf('The route "%s" already declares its metadata.', $routePath->pattern()),
            );
        }

        $this->meta = $meta;
    }

    /**
     * The declared name, or null when the route is unnamed.
     */
    public function declaredName(): ?string
    {
        return $this->name;
    }

    /**
     * The declared constraints map, or null when none were declared.
     *
     * @return null|array<string, string>
     */
    public function declaredConstraints(): ?array
    {
        return $this->constraints;
    }

    /**
     * The declared metadata map, or null when none was declared.
     *
     * @return null|array<array-key, mixed>
     */
    public function declaredMeta(): ?array
    {
        return $this->meta;
    }
}
