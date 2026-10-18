<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zef\Framework\Exception\ZefException;

/** Renders an escaped, branded HTML error page for browsers. */
final class HtmlErrorPageRenderer
{
    public static function render(
        Throwable $throwable,
        int $status,
        string $detail,
        string $instance,
        string $traceId,
        bool $debug,
        float $durationMs = 0.0,
        int $queryCount = 0,
        int $exceptionCount = 0,
    ): ResponseInterface {
        $title = ProblemDetailsMetadata::titleFor($status);
        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . self::escape($title) . '</title>'
            . '<style>'
            . 'body{font:16px/1.5 system-ui,sans-serif;max-width:56rem;'
            . 'margin:10vh auto;padding:0 1.5rem;color:#20242a}'
            . 'code,pre{background:#f2f4f7;padding:.25rem .4rem;border-radius:.25rem}'
            . 'pre{overflow:auto;padding:1rem}'
            . '.status{color:#667085;font-variant-numeric:tabular-nums}'
            . '#zef-debug-toolbar{position:fixed;bottom:0;left:0;right:0;background:#111827;color:#f9fafb;'
            . 'padding:.65rem 1rem;font:12px/1.5 ui-monospace,monospace;display:flex;gap:1.25rem;'
            . 'flex-wrap:wrap;z-index:2147483647}'
            . '#zef-debug-toolbar a{color:#93c5fd}'
            . '.zef-debug-panel{position:fixed;top:0;right:0;width:min(42rem,92vw);height:100vh;'
            . 'box-sizing:border-box;overflow:auto;padding:1.5rem;background:#fff;color:#20242a;'
            . 'box-shadow:-8px 0 32px rgba(0,0,0,.25);transform:translateX(105%);'
            . 'transition:transform .15s ease;z-index:2147483646}'
            . '.zef-debug-panel:target{transform:translateX(0)}'
            . '.zef-debug-panel pre{white-space:pre-wrap;overflow-wrap:anywhere}'
            . '.zef-debug-panel a{color:#175cd3}</style></head><body>'
            . '<p><strong>ZEF Framework</strong></p><h1>' . self::escape($title) . '</h1>'
            . '<p>' . self::escape($detail) . '</p>'
            . '<p class="status">HTTP ' . $status . ' · Request ' . self::escape($traceId) . '</p>';

        if ($debug) {
            $body .= '<p>Path: <code>' . self::escape($instance !== '' ? $instance : '/') . '</code></p>'
                . '<section id="zef-error-details" class="zef-debug-panel" aria-label="Exception details">'
                . '<h2>Exception details</h2><pre>' . self::escape(
                    RedactedMessage::of($throwable::class . ': ' . $throwable->getMessage()
                        . "\n" . $throwable->getTraceAsString()),
                ) . '</pre><h2>Related server log</h2>'
                . '<p>Search the JSONL error log for this request ID:</p><code>bin/zef errors:report --request-id='
                . self::escape($traceId) . '</code></section>';

            if ($throwable instanceof ZefException && $throwable->getDetails() !== []) {
                $details = json_encode(
                    ErrorDetailsSanitizer::sanitize($throwable->getDetails()),
                    JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE,
                );
                $body .= '<h2>Developer context</h2><pre>'
                    . self::escape(is_string($details) ? $details : '{}') . '</pre>';
            }
        }

        if ($debug) {
            $body .= '<aside id="zef-debug-toolbar" aria-label="ZEF debug toolbar">'
                . '<strong>ZEF Profiler</strong>'
                . '<span>Duration: ' . number_format(max(0.0, $durationMs), 2, '.', '') . ' ms</span>'
                . '<span>DB queries: ' . max(0, $queryCount) . '</span>'
                . '<span>Exceptions: ' . max(0, $exceptionCount) . '</span>'
                . '<span>Trace: ' . self::escape($traceId) . '</span>'
                . '<a href="#zef-error-details">Exception details</a>'
                . '</aside>';
        }

        $body .= '</body></html>';

        return new Response($status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; "
                . "base-uri 'none'; frame-ancestors 'none'",
        ], $body);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
