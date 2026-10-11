<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * Renders one route parameter from the given parameter map into its
 * URL segment.
 *
 * The value must be given (a missing parameter is a defect in the
 * caller's table of names), must be scalar or `Stringable` and
 * stringify to something (an empty segment can never round-trip its own
 * route -- the string form is narrowed by {@see RouteParameterString}),
 * and must satisfy the route's constraint -- judged on the raw value,
 * before the encoding, because the engine matches raw path text
 * (KDD-R6): a generated URL re-matches its route, and the captured raw
 * value is the `rawurlencode`d form of the given value. A typical
 * constraint accepts only characters the encoding preserves, so what it
 * accepts it also accepts after encoding.
 *
 * @internal
 */
final class RouteParameterRenderer
{
    /**
     * The validated, constraint-checked, encoded URL segment for one
     * route parameter.
     *
     * @param array<string, mixed> $params the given parameter map
     */
    public static function render(CompiledRoute $compiledRoute, string $parameterName, array $params): string
    {
        if (!\array_key_exists($parameterName, $params)) {
            throw new ConfigException(
                \sprintf(
                    'The route "%s" requires the parameter "%s".',
                    $compiledRoute->path()->pattern(),
                    $parameterName,
                ),
            );
        }

        $string = RouteParameterString::of($compiledRoute, $parameterName, $params[$parameterName]);

        if (!$compiledRoute->constraints()->check($parameterName, $string)) {
            throw new ConfigException(
                \sprintf(
                    'The value "%s" of the parameter "%s" violates the constraint of the route "%s".',
                    $string,
                    $parameterName,
                    $compiledRoute->path()->pattern(),
                ),
            );
        }

        return rawurlencode($string);
    }
}
