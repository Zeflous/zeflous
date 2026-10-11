<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The constraint-map half of a route declaration: the parameter rules a
 * declaration carries, checked against the route's own parsed path.
 *
 * A constraint for a name the pattern never declares is a typo that must
 * not survive to the engine, and a pattern that is not a non-empty
 * string is not a pattern -- both fail closed here, on the declaration
 * line, with the parameter and route in the message. Whether a pattern
 * compiles and anchors is the engine's judgement, made once at
 * {@see RadixTree::compile()} time.
 *
 * @internal
 */
final class RouteDslConstraints
{
    /**
     * Checks every declared constraint against the route's own parameter
     * names and builds the typed map; a name that is not a string is a
     * list-shaped declaration.
     *
     * @param array<array-key, mixed> $constraints
     *
     * @return array<string, string>
     */
    public static function validated(array $constraints, RoutePath $routePath): array
    {
        $validated = [];

        foreach ($constraints as $name => $pattern) {
            if (!\is_string($name)) {
                throw new ConfigException(
                    \sprintf(
                        'The route "%s" must declare constraints as a parameter-name map.',
                        $routePath->pattern(),
                    ),
                );
            }

            if (!\in_array($name, $routePath->parameterNames(), true)) {
                throw new ConfigException(
                    \sprintf(
                        'The route "%s" has no parameter "%s" to constrain.',
                        $routePath->pattern(),
                        $name,
                    ),
                );
            }

            if (!\is_string($pattern) || $pattern === '') {
                throw new ConfigException(
                    \sprintf(
                        'The constraint "%s" on route "%s" must be a non-empty pattern.',
                        $name,
                        $routePath->pattern(),
                    ),
                );
            }

            $validated[$name] = $pattern;
        }

        return $validated;
    }
}
