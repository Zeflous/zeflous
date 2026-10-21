<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The routing-relevant view of one PSR-7 request: the method, path, host,
 * Accept header and API-version header the router asks about, extracted
 * once so the HEAD-over-GET fallback re-matches without re-reading the
 * request.
 *
 * @internal
 */
final readonly class ServerRequestMetadata
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $host,
        public string $accept,
        public string $version,
    ) {
    }

    public static function fromRequest(ServerRequestInterface $serverRequest): self
    {
        return new self(
            mb_strtoupper($serverRequest->getMethod()),
            self::requestPath($serverRequest),
            RequestAuthority::host($serverRequest),
            $serverRequest->getHeaderLine('Accept'),
            self::requestVersion($serverRequest),
        );
    }

    private static function requestPath(ServerRequestInterface $serverRequest): string
    {
        $path = $serverRequest->getUri()->getPath();

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    private static function requestVersion(ServerRequestInterface $serverRequest): string
    {
        $acceptVersion = $serverRequest->getHeaderLine('Accept-Version');

        return $acceptVersion !== '' ? $acceptVersion : $serverRequest->getHeaderLine('X-API-Version');
    }
}
