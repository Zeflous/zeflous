<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\HtmlErrorPageContext;
use Zef\Framework\Http\HtmlErrorPageRenderer;
use Zef\Framework\Http\ProblemDetailsMetadata;
use Zef\Framework\Http\RedactedMessage;
use Zef\Framework\Http\Response;
use Zef\Test\Support\Exception\ExplanatoryException;

/**
 * Golden-master coverage for the browser-facing error page: the full
 * document is asserted byte-for-byte in production and debug shapes, so
 * every markup literal, escape flag, numeric bound, and branch boundary
 * is pinned exactly.
 *
 * @internal
 */
#[CoversClass(HtmlErrorPageRenderer::class)]
#[CoversClass(HtmlErrorPageContext::class)]
#[CoversClass(ProblemDetailsMetadata::class)]
#[CoversClass(RedactedMessage::class)]
#[CoversClass(Response::class)]
final class HtmlErrorPageRendererTest extends TestCase
{
    /**
     * The static document prologue before the page title.
     */
    private const string DOCUMENT_BEFORE_TITLE = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>';

    /**
     * The static document prologue after the page title, through the
     * framework byline.
     */
    private const string DOCUMENT_AFTER_TITLE = '</title>'
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
        . '<p><strong>ZEF Framework</strong></p>';

    public function testAProductionPageRendersTheExactEscapedDocument(): void
    {
        $response = HtmlErrorPageRenderer::render(
            new RuntimeException('the production page never renders the throwable'),
            new HtmlErrorPageContext(404, "Broken \"quote\" & <tag> \xB1", '/unused-in-prod', "req-\"'<>&", false),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'",
            $response->getHeaderLine('Content-Security-Policy'),
        );
        self::assertSame(
            self::DOCUMENT_BEFORE_TITLE . 'Resource Not Found' . self::DOCUMENT_AFTER_TITLE
            . '<h1>Resource Not Found</h1>'
            . '<p>Broken &quot;quote&quot; &amp; &lt;tag&gt; ' . "\u{FFFD}" . '</p>'
            . '<p class="status">HTTP 404 · Request req-&quot;&#039;&lt;&gt;&amp;</p>'
            . '</body></html>',
            (string) $response->getBody(),
        );
    }

    public function testADebugPageWithDeveloperContextRendersTheExactDocument(): void
    {
        $exception = new ExplanatoryException(
            'Failed safely.',
            0,
            null,
            'FAILED_SAFELY',
            ['credential' => 'password=secret123'],
        );

        $response = HtmlErrorPageRenderer::render(
            $exception,
            new HtmlErrorPageContext(500, 'Failed safely.', '/orders/42', "req-\"'<>&", true, 1234.5, 7, 2),
        );

        $diagnostics = htmlspecialchars(
            RedactedMessage::of($exception::class . ': ' . $exception->getMessage()
                . "\n" . $exception->getTraceAsString()),
            \ENT_QUOTES | \ENT_SUBSTITUTE,
            'UTF-8',
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            self::DOCUMENT_BEFORE_TITLE . 'Internal Server Error' . self::DOCUMENT_AFTER_TITLE
            . '<h1>Internal Server Error</h1>'
            . '<p>Failed safely.</p>'
            . '<p class="status">HTTP 500 · Request req-&quot;&#039;&lt;&gt;&amp;</p>'
            . '<p>Path: <code>/orders/42</code></p>'
            . '<section id="zef-error-details" class="zef-debug-panel" aria-label="Exception details">'
            . '<h2>Exception details</h2><pre>' . $diagnostics . '</pre><h2>Related server log</h2>'
            . '<p>Search the JSONL error log for this request ID:</p><code>bin/zef errors:report --request-id='
            . 'req-&quot;&#039;&lt;&gt;&amp;</code>'
            . '<h2>Developer context</h2><pre>{' . "\n"
            . '    &quot;credential&quot;: &quot;password=[REDACTED]&quot;' . "\n"
            . '}</pre></section>'
            . '<aside id="zef-debug-toolbar" aria-label="ZEF debug toolbar">'
            . '<strong>ZEF Profiler</strong>'
            . '<span>Duration: 1234.50 ms</span>'
            . '<span>DB queries: 7</span>'
            . '<span>Exceptions: 2</span>'
            . '<span>Trace: req-&quot;&#039;&lt;&gt;&amp;</span>'
            . '<a href="#zef-error-details">Exception details</a>'
            . '</aside></body></html>',
            (string) $response->getBody(),
        );
    }

    public function testADebugPageWithoutAnInstanceFallsBackToTheRootPathAndZeroedCounters(): void
    {
        $exception = new RuntimeException('boom');

        $response = HtmlErrorPageRenderer::render(
            $exception,
            new HtmlErrorPageContext(500, 'The application failed safely.', '', 'req-123', true),
        );

        $diagnostics = htmlspecialchars(
            RedactedMessage::of($exception::class . ': ' . $exception->getMessage()
                . "\n" . $exception->getTraceAsString()),
            \ENT_QUOTES | \ENT_SUBSTITUTE,
            'UTF-8',
        );

        self::assertSame(
            self::DOCUMENT_BEFORE_TITLE . 'Internal Server Error' . self::DOCUMENT_AFTER_TITLE
            . '<h1>Internal Server Error</h1>'
            . '<p>The application failed safely.</p>'
            . '<p class="status">HTTP 500 · Request req-123</p>'
            . '<p>Path: <code>/</code></p>'
            . '<section id="zef-error-details" class="zef-debug-panel" aria-label="Exception details">'
            . '<h2>Exception details</h2><pre>' . $diagnostics . '</pre><h2>Related server log</h2>'
            . '<p>Search the JSONL error log for this request ID:</p><code>bin/zef errors:report --request-id='
            . 'req-123</code></section>'
            . '<aside id="zef-debug-toolbar" aria-label="ZEF debug toolbar">'
            . '<strong>ZEF Profiler</strong>'
            . '<span>Duration: 0.00 ms</span>'
            . '<span>DB queries: 0</span>'
            . '<span>Exceptions: 0</span>'
            . '<span>Trace: req-123</span>'
            . '<a href="#zef-error-details">Exception details</a>'
            . '</aside></body></html>',
            (string) $response->getBody(),
        );
    }

    public function testADebugPageClampsNegativeProfilerCountersToZero(): void
    {
        $exception = new RuntimeException('boom');

        $response = HtmlErrorPageRenderer::render(
            $exception,
            new HtmlErrorPageContext(500, 'x', '/x', 'req-1', true, -25.7, -3, -2),
        );

        $diagnostics = htmlspecialchars(
            RedactedMessage::of($exception::class . ': ' . $exception->getMessage()
                . "\n" . $exception->getTraceAsString()),
            \ENT_QUOTES | \ENT_SUBSTITUTE,
            'UTF-8',
        );

        self::assertSame(
            self::DOCUMENT_BEFORE_TITLE . 'Internal Server Error' . self::DOCUMENT_AFTER_TITLE
            . '<h1>Internal Server Error</h1>'
            . '<p>x</p>'
            . '<p class="status">HTTP 500 · Request req-1</p>'
            . '<p>Path: <code>/x</code></p>'
            . '<section id="zef-error-details" class="zef-debug-panel" aria-label="Exception details">'
            . '<h2>Exception details</h2><pre>' . $diagnostics . '</pre><h2>Related server log</h2>'
            . '<p>Search the JSONL error log for this request ID:</p><code>bin/zef errors:report --request-id='
            . 'req-1</code></section>'
            . '<aside id="zef-debug-toolbar" aria-label="ZEF debug toolbar">'
            . '<strong>ZEF Profiler</strong>'
            . '<span>Duration: 0.00 ms</span>'
            . '<span>DB queries: 0</span>'
            . '<span>Exceptions: 0</span>'
            . '<span>Trace: req-1</span>'
            . '<a href="#zef-error-details">Exception details</a>'
            . '</aside></body></html>',
            (string) $response->getBody(),
        );
    }

    public function testADebugPageAnswersAnEmptyObjectWhenDetailsCannotBeEncoded(): void
    {
        $response = HtmlErrorPageRenderer::render(
            new ExplanatoryException('nan', 0, null, 'NAN_DETAILS', ['budget' => \NAN]),
            new HtmlErrorPageContext(500, 'x', '/x', 'req-1', true, 1.0, 1, 1),
        );

        self::assertStringContainsString(
            '<h2>Developer context</h2><pre>{}</pre>',
            (string) $response->getBody(),
        );
    }

    public function testAFreshContextDefaultsItsProfilerCountersToZero(): void
    {
        $context = new HtmlErrorPageContext(500, 'detail', '/instance', 'req-1', false);

        self::assertSame(0.0, $context->durationMilliseconds);
        self::assertSame(0, $context->queryCount);
        self::assertSame(0, $context->exceptionCount);
    }
}
