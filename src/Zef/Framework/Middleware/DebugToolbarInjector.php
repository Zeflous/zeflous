<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamSnapshot;

/** Injects the toolbar into one bounded HTML response without mutating its original stream. */
final class DebugToolbarInjector
{
    public static function inject(
        ResponseInterface $response,
        float $durationMs,
        int $queries,
        int $exceptions,
        string $traceId,
    ): ResponseInterface {
        $html = StreamSnapshot::capture($response->getBody());
        if ($html === null) {
            return $response;
        }

        $toolbar = DebugToolbarMarkup::build($durationMs, $queries, $exceptions, $traceId);
        $updated = DebugToolbarBodySplice::insertBeforeClosingBody($html, $toolbar);
        if ($updated === null) {
            return $response;
        }

        try {
            $body = Stream::fromString($updated);
        } catch (Throwable) {
            return $response;
        }

        $updatedResponse = $response->withBody($body)
            ->withoutHeader('Content-Length')
            ->withoutHeader('ETag')
            ->withoutHeader('Content-MD5')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'",
            )
            ->withHeader('X-Debug-Toolbar', 'enabled')
        ;

        return $updatedResponse instanceof ResponseInterface ? $updatedResponse : $response;
    }
}
