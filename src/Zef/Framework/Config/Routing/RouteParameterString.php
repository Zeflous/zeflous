<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Stringable;
use Zef\Framework\Config\ConfigException;

/**
 * The string form of one route parameter value: scalar or `Stringable`
 * -- and never empty, because an empty segment can never round-trip its
 * own route.
 *
 * @internal
 */
final class RouteParameterString
{
    /**
     * Narrows one parameter value to its non-empty string form.
     */
    public static function of(CompiledRoute $compiledRoute, string $parameterName, mixed $value): string
    {
        if (!\is_scalar($value) && !$value instanceof Stringable) {
            throw new ConfigException(
                \sprintf(
                    'The parameter "%s" of the route "%s" must be scalar or Stringable, got %s.',
                    $parameterName,
                    $compiledRoute->path()->pattern(),
                    get_debug_type($value),
                ),
            );
        }

        $string = (string) $value;

        if ($string === '') {
            throw new ConfigException(
                \sprintf(
                    'The parameter "%s" of the route "%s" must not be empty.',
                    $parameterName,
                    $compiledRoute->path()->pattern(),
                ),
            );
        }

        return $string;
    }
}
