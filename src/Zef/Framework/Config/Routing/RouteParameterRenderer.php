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
 * and must satisfy the route's constraint twice: on the raw value, and
 * on the `rawurlencode`d form the engine actually re-matches -- the
 * engine judges path text without decoding (KDD-R6), so the segment a
 * generated URL resolves back to is the encoded spelling. A constraint
 * that accepts the raw value but refuses its encoded form would emit a
 * URL that 404s against its own route, breaking the self-validation
 * promise (`ROADMAP.md` -- "Self-Validation"), so generation fails
 * closed here instead: the exception names the route, the parameter and
 * both spellings of the value.
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

        $encoded = rawurlencode($string);

        if (!$compiledRoute->constraints()->check($parameterName, $encoded)) {
            throw new ConfigException(
                \sprintf(
                    'The encoded form "%s" of the value "%s" of the parameter "%s" violates the constraint'
                        . ' of the route "%s": the engine matches the encoded path, so a URL with this segment'
                        . ' could not resolve back to the route.',
                    $encoded,
                    $string,
                    $parameterName,
                    $compiledRoute->path()->pattern(),
                ),
            );
        }

        return $encoded;
    }
}
