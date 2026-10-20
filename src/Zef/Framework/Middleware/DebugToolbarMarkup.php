<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

/** Builds the escaped toolbar markup shown on debug HTML responses. */
final class DebugToolbarMarkup
{
    public static function build(float $durationMs, int $queries, int $exceptions, string $traceId): string
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
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
