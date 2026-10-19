<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zef\Framework\Http\ErrorContentNegotiator;

/** Decides whether a response is safe to decorate with the development toolbar. */
final class DebugToolbarEligibility
{
    private const int MAX_HTML_BYTES = 2_000_000;

    public static function allows(ServerRequestInterface $serverRequest, ResponseInterface $response): bool
    {
        return mb_strtoupper($serverRequest->getMethod()) !== 'HEAD'
            && ErrorContentNegotiator::prefersHtml($serverRequest->getHeaderLine('Accept'))
            && !$response->hasHeader('Content-Security-Policy')
            && str_starts_with(mb_strtolower($response->getHeaderLine('Content-Type')), 'text/html')
            && $response->getStatusCode() >= 200
            && $response->getStatusCode() < 300
            && $response->getHeaderLine('Content-Encoding') === ''
            && $response->getBody()->isSeekable()
            && ($response->getBody()->getSize() ?? \PHP_INT_MAX) <= self::MAX_HTML_BYTES;
    }
}
