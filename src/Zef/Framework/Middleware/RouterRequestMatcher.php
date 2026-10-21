<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Router\Router;

/**
 * The PSR-7-to-match face of the routing adapter: extracts the method,
 * path, host, Accept and API-version a request carries, asks the router,
 * and applies the HEAD-over-GET fallback when no explicit HEAD route
 * exists but a GET route does.
 *
 * @internal
 */
final class RouterRequestMatcher
{
    public function __construct(private Router $router)
    {
    }

    /**
     * Matches the request through the router, reusing GET metadata for a
     * HEAD request whose only route is the GET one.
     */
    public function match(ServerRequestMetadata $metadata): MatchResult
    {
        $match = $this->router->match(
            $metadata->method,
            $metadata->path,
            $metadata->host,
            $metadata->accept,
            $metadata->version,
        );

        if (
            $metadata->method === 'HEAD'
            && $match->isMethodNotAllowed()
            && \in_array('GET', $match->allowedMethods(), true)
        ) {
            return $this->router->match(
                'GET',
                $metadata->path,
                $metadata->host,
                $metadata->accept,
                $metadata->version,
            );
        }

        return $match;
    }
}
