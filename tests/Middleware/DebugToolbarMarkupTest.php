<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Middleware\DebugToolbarMarkup;

/**
 * Golden-master coverage for the standalone debug toolbar: the complete
 * markup is asserted byte-for-byte, including the escaped trace id, the
 * fixed-point duration formatting, and the clamping of negative counters.
 *
 * @internal
 */
#[CoversClass(DebugToolbarMarkup::class)]
final class DebugToolbarMarkupTest extends TestCase
{
    public function testTheToolbarRendersTheExactEscapedMarkup(): void
    {
        self::assertSame(
            '<aside id="zef-debug-toolbar" aria-label="ZEF debug toolbar" '
            . 'style="position:fixed;bottom:0;left:0;right:0;background:#111827;color:#f9fafb;'
            . 'padding:.65rem 1rem;font:12px/1.5 ui-monospace,monospace;display:flex;gap:1.25rem;'
            . 'flex-wrap:wrap;z-index:2147483647">'
            . '<strong>ZEF Profiler</strong>'
            . '<span>Duration: 1234.50 ms</span>'
            . '<span>DB queries: 7</span>'
            . '<span>Exceptions: 2</span>'
            . '<span>Trace: req-&quot;&#039;&lt;&gt;&amp;</span>'
            . '<details><summary>Request / log details</summary>'
            . '<p>Find related server-side records by request ID:</p><code>bin/zef errors:report --request-id='
            . 'req-&quot;&#039;&lt;&gt;&amp;</code></details>'
            . '</aside>',
            DebugToolbarMarkup::build(1234.5, 7, 2, "req-\"'<>&"),
        );
    }

    public function testTheToolbarClampsNegativeCountersToZero(): void
    {
        self::assertSame(
            '<aside id="zef-debug-toolbar" aria-label="ZEF debug toolbar" '
            . 'style="position:fixed;bottom:0;left:0;right:0;background:#111827;color:#f9fafb;'
            . 'padding:.65rem 1rem;font:12px/1.5 ui-monospace,monospace;display:flex;gap:1.25rem;'
            . 'flex-wrap:wrap;z-index:2147483647">'
            . '<strong>ZEF Profiler</strong>'
            . '<span>Duration: 0.00 ms</span>'
            . '<span>DB queries: 0</span>'
            . '<span>Exceptions: 0</span>'
            . '<span>Trace: req-1</span>'
            . '<details><summary>Request / log details</summary>'
            . '<p>Find related server-side records by request ID:</p><code>bin/zef errors:report --request-id='
            . 'req-1</code></details>'
            . '</aside>',
            DebugToolbarMarkup::build(-25.7, -3, -2, 'req-1'),
        );
    }
}
