<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\ErrorContentNegotiator;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\Stream;

/**
 * Appends a bounded, development-only toolbar to small seekable HTML responses.
 * Binary, compressed, streamed and non-HTML responses are passed through intact.
 */
final readonly class DebugToolbarMiddleware implements MiddlewareInterface
{
    private const int MAX_HTML_BYTES = 2_000_000;

    public function __construct(private bool $enabled = false)
    {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $startedAt = hrtime(true);
        $response = $handler->handle($request);

        if (
            strtoupper($request->getMethod()) === 'HEAD'
            || !ErrorContentNegotiator::prefersHtml($request->getHeaderLine('Accept'))
            || !str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/html')
            || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
            || $response->getHeaderLine('Content-Encoding') !== ''
            || !$response->getBody()->isSeekable()
            || ($response->getBody()->getSize() ?? PHP_INT_MAX) > self::MAX_HTML_BYTES
        ) {
            return $response;
        }

        $context = $request->getAttribute(ErrorRequestContext::ATTRIBUTE);
        $duration = $context instanceof ErrorRequestContext
            ? $context->durationMilliseconds()
            : (hrtime(true) - $startedAt) / 1_000_000;
        $traceId = $context instanceof ErrorRequestContext ? $context->traceId() : CorrelationId::for($request);
        $queries = $context instanceof ErrorRequestContext ? $context->queryCount() : 0;
        $exceptions = $context instanceof ErrorRequestContext ? $context->exceptionCount() : 0;

        $stream = $response->getBody();
        try {
            $position = $stream->tell();
            $stream->rewind();
            $html = $stream->getContents();
        } catch (Throwable) {
            if (isset($position)) {
                try {
                    $stream->seek($position);
                } catch (Throwable) {
                    // Keep the original response when stream restoration fails.
                }
            }

            return $response;
        }

        try {
            $stream->seek($position);
        } catch (Throwable) {
            return $response;
        }

        $toolbar = self::toolbar($duration, $queries, $exceptions, $traceId);
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

        $written = fwrite($resource, $updated);
        if ($written !== strlen($updated) || !rewind($resource)) {
            fclose($resource);

            return $response;
        }

        $response = $response->withBody(new Stream($resource));
        $response = $response->withoutHeader('Content-Length')
            ->withoutHeader('ETag')
            ->withoutHeader('Content-MD5')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'")
            ->withHeader('X-Debug-Toolbar', 'enabled');

        return $response;
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
            . '</aside>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
