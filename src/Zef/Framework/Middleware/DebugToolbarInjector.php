<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zef\Framework\Http\Stream;

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
        $html = self::readHtml($response);
        if ($html === null) {
            return $response;
        }

        $toolbar = self::toolbar($durationMs, $queries, $exceptions, $traceId);
        $count = 0;
        $updated = preg_replace_callback(
            '/<\/body\s*>/i',
            static fn (array $match): string => $toolbar . $match[0],
            $html,
            1,
            $count,
        );
        if (!is_string($updated) || $count !== 1) {
            return $response;
        }

        $resource = fopen('php://temp', 'r+b');
        if ($resource === false) {
            return $response;
        }

        if (fwrite($resource, $updated) !== strlen($updated) || !rewind($resource)) {
            fclose($resource);

            return $response;
        }

        return $response->withBody(new Stream($resource))
            ->withoutHeader('Content-Length')
            ->withoutHeader('ETag')
            ->withoutHeader('Content-MD5')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'",
            )
            ->withHeader('X-Debug-Toolbar', 'enabled');
    }

    private static function readHtml(ResponseInterface $response): ?string
    {
        $stream = $response->getBody();
        try {
            $position = $stream->tell();
            $stream->rewind();
            $html = $stream->getContents();
        } catch (Throwable) {
            if (isset($position)) {
                self::restore($stream, $position);
            }

            return null;
        }

        return self::restore($stream, $position) ? $html : null;
    }

    private static function restore(\Psr\Http\Message\StreamInterface $stream, int $position): bool
    {
        try {
            $stream->seek($position);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function toolbar(float $durationMs, int $queries, int $exceptions, string $traceId): string
    {
        return '<aside id="zef-debug-toolbar" aria-label="ZEF debug toolbar" '
            . 'style="position:fixed;bottom:0;left:0;right:0;background:#111827;color:#f9fafb;'
            . 'padding:.65rem 1rem;font:12px/1.5 ui-monospace,monospace;display:flex;gap:1.25rem;'
            . 'flex-wrap:wrap;z-index:2147483647">'
            . '<strong>ZEF Profiler</strong>'
            . '<span>Duration: ' . number_format(max(0.0, $durationMs), 2, '.', '') . ' ms</span>'
            . '<span>DB queries: ' . max(0, $queries) . '</span>'
            . '<span>Exceptions: ' . max(0, $exceptions) . '</span>'
            . '<span>Trace: ' . self::escape($traceId) . '</span>'
            . '<details><summary>Request / log details</summary>'
            . '<p>Find related server-side records by request ID:</p><code>bin/zef errors:report --request-id='
            . self::escape($traceId) . '</code></details>'
            . '</aside>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
