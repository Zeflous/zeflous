<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Container\Container;

/**
 * The matched route's middleware stack, resolved fail-closed: the
 * single-question fold the router's request handler composes its
 * inner pipeline from -- each middleware service the matched route
 * declares, resolved through the PSR-11 container, an unregistered
 * service or a non-middleware service refused with the service
 * named.
 *
 * A non-found match answers the empty stack: the handler's terminal
 * dispatch owns the refusal translation, and no middleware of a
 * route that did not match ever runs.
 *
 * @internal
 */
final class RouteMiddlewareStack
{
    /**
     * Resolves the matched route's middleware, in the order stored
     * on the route.
     *
     * @return list<MiddlewareInterface>
     */
    public static function resolve(MatchResult $matchResult, Container $container): array
    {
        if (!$matchResult->isFound()) {
            return [];
        }

        $stack = [];

        foreach ($matchResult->route()->middleware() as $serviceId) {
            if (!$container->has($serviceId)) {
                throw new MiddlewareException(\sprintf(
                    'Route middleware service "%s" is not registered.',
                    $serviceId,
                ));
            }

            $middleware = $container->get($serviceId);
            if (!$middleware instanceof MiddlewareInterface) {
                throw new MiddlewareException(\sprintf(
                    'Route middleware service "%s" does not implement MiddlewareInterface.',
                    $serviceId,
                ));
            }

            $stack[] = $middleware;
        }

        return $stack;
    }
}
