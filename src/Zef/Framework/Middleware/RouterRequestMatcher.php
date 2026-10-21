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
final readonly class RouterRequestMatcher
{
    public function __construct(private Router $router)
    {
    }

    /**
     * Matches the request through the router, reusing GET metadata for a
     * HEAD request whose only route is the GET one.
     */
    public function match(ServerRequestMetadata $serverRequestMetadata): MatchResult
    {
        $match = $this->router->match(
            $serverRequestMetadata->method,
            $serverRequestMetadata->path,
            $serverRequestMetadata->host,
            $serverRequestMetadata->accept,
            $serverRequestMetadata->version,
        );

        if (
            $serverRequestMetadata->method === 'HEAD'
            && $match->isMethodNotAllowed()
            && \in_array('GET', $match->allowedMethods(), true)
        ) {
            return $this->router->match(
                'GET',
                $serverRequestMetadata->path,
                $serverRequestMetadata->host,
                $serverRequestMetadata->accept,
                $serverRequestMetadata->version,
            );
        }

        return $match;
    }
}
