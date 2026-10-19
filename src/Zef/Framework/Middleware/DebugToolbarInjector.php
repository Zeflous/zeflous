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
        $body = self::updatedBody($response, $durationMs, $queries, $exceptions, $traceId);
        if (!$body instanceof Stream) {
            return $response;
        }

        $message = $response->withBody($body)
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

        return $message instanceof ResponseInterface ? $message : $response;
    }

    /**
     * The toolbar-spliced replacement body, or null when the response must
     * pass through untouched: a snapshot the injector cannot take, a body
     * without a closing tag to splice before, or a stream the canonical
     * seam refuses to build.
     *
     * @return null|Stream the updated response body
     */
    private static function updatedBody(
        ResponseInterface $response,
        float $durationMs,
        int $queries,
        int $exceptions,
        string $traceId,
    ): ?Stream {
        $html = StreamSnapshot::capture($response->getBody());
        if ($html === null) {
            return null;
        }

        $toolbar = DebugToolbarMarkup::build($durationMs, $queries, $exceptions, $traceId);
        $spliced = DebugToolbarBodySplice::insertBeforeClosingBody($html, $toolbar);

        if ($spliced !== null) {
            try {
                return Stream::fromString($spliced);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
