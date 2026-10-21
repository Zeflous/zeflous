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

    public static function fromRequest(ServerRequestInterface $request): self
    {
        return new self(
            mb_strtoupper($request->getMethod()),
            self::requestPath($request),
            RequestAuthority::host($request),
            $request->getHeaderLine('Accept'),
            self::requestVersion($request),
        );
    }

    private static function requestPath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    private static function requestVersion(ServerRequestInterface $request): string
    {
        $acceptVersion = $request->getHeaderLine('Accept-Version');

        return $acceptVersion !== '' ? $acceptVersion : $request->getHeaderLine('X-API-Version');
    }
}
