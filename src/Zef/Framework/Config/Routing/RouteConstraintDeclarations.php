<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The constraint declarations' validation fold: the one question the
 * constraints value object asks of a raw constraint map -- is every
 * declared name a parameter the pattern owns, and every declared
 * pattern one the engine can compile?
 *
 * Registration is the moment to judge, not the match (see
 * `ROADMAP.md`, "Configuration System + DSL + Radix Tree"):
 * a constraint naming a parameter the pattern does not contain, or
 * a pattern the engine cannot compile, fails the whole route table
 * with a {@see ConfigException} naming the route and the offending
 * parameter. The fold answers the two storages the value object
 * carries: the anchored patterns the match judges segments with, and
 * the verbatim declarations the export reads
 * ({@see RouteConstraints::pattern()}).
 *
 * @internal consumed through {@see RouteConstraints}
 */
final readonly class RouteConstraintDeclarations
{
    /**
     * Validates a constraint map against the route's parsed path.
     *
     * @param array<string, non-empty-string> $constraints parameter name => pattern
     *
     * @return array{0: array<string, non-empty-string>, 1: array<string, non-empty-string>}
     *
     * @throws ConfigException when a constraint names an unknown parameter or its pattern does not compile
     */
    public static function validated(array $constraints, RoutePath $routePath): array
    {
        $names = array_fill_keys($routePath->parameterNames(), true);
        $patterns = [];
        $declared = [];

        foreach ($constraints as $name => $pattern) {
            if (!isset($names[$name])) {
                throw new ConfigException(
                    \sprintf(
                        'The route pattern "%s" constrains the unknown parameter "%s".',
                        $routePath->pattern(),
                        $name,
                    ),
                );
            }

            $anchored = '/^(?:' . $pattern . ')\z/';

            if (!RouteConstraintCompilability::probe($anchored)) {
                throw new ConfigException(
                    \sprintf(
                        'The route pattern "%s" has an invalid constraint pattern for the parameter "%s".',
                        $routePath->pattern(),
                        $name,
                    ),
                );
            }

            $patterns[$name] = $anchored;
            $declared[$name] = $pattern;
        }

        return [$patterns, $declared];
    }
}
