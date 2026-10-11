<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The set of parameters a route accepts: the route's own parameter
 * names, against which a given parameter map is judged.
 *
 * A parameter the route never asked for is a defect in the caller's
 * table of names, not something to silently drop -- generation fails
 * closed exactly like compilation.
 *
 * @internal
 */
final class RouteParameterSet
{
    /**
     * Rejects parameters the route never asked for.
     *
     * @param array<string, mixed> $params the given parameter map
     */
    public static function assertNoExcess(CompiledRoute $compiledRoute, array $params): void
    {
        $accepted = array_flip($compiledRoute->path()->parameterNames());

        foreach (array_keys($params) as $parameterName) {
            if (!isset($accepted[$parameterName])) {
                throw new ConfigException(
                    \sprintf(
                        'The route "%s" does not accept the parameter "%s".',
                        $compiledRoute->path()->pattern(),
                        $parameterName,
                    ),
                );
            }
        }
    }
}
