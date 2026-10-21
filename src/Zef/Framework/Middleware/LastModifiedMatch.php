<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The If-Modified-Since condition: whether the representation is not
 * newer than the date the client already holds. Unparsable dates on
 * either side never satisfy the condition, and an absent header is no
 * condition at all.
 *
 * @internal
 */
final class LastModifiedMatch
{
    public static function satisfied(ServerRequestInterface $serverRequest, ResponseInterface $response): bool
    {
        if (!$serverRequest->hasHeader('If-Modified-Since')) {
            return false;
        }

        $lastModified = strtotime($response->getHeaderLine('Last-Modified'));
        $ifModifiedSince = strtotime($serverRequest->getHeaderLine('If-Modified-Since'));

        return $lastModified !== false && $ifModifiedSince !== false && $lastModified <= $ifModifiedSince;
    }
}
