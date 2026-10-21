<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the request host a router should match: the Host header's
 * authority (host, with an optional port the grammar validates) when the
 * client sent one, the URI host otherwise. A malformed authority is a
 * runtime miss answered as "no host", never an error.
 *
 * @internal
 */
final class RequestAuthority
{
    public static function host(ServerRequestInterface $serverRequest): ?string
    {
        if (!$serverRequest->hasHeader('Host')) {
            $uriHost = $serverRequest->getUri()->getHost();

            return $uriHost === '' ? null : $uriHost;
        }

        $authority = mb_trim($serverRequest->getHeaderLine('Host'));
        if (preg_match('/\A([^:]+)(?::([0-9]{1,5}))?\z/D', $authority, $matches) !== 1) {
            return null;
        }

        if (isset($matches[2]) && ((int) $matches[2] < 1 || (int) $matches[2] > 65535)) {
            return null;
        }

        return $matches[1];
    }
}
