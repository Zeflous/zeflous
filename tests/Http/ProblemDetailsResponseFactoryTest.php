<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ProblemDetailsResponseFactory;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

/**
 * Pins the browser error page's profiler counters at the factory seam: an
 * attached request context contributes its live duration, query count and
 * exception count, and a request without one falls back to the factory's
 * own defaults (the failing request itself counts as the first exception).
 * The page's markup, escaping and negative-counter clamping are pinned by
 * the renderer's golden-master battery; this battery pins the ternaries
 * that decide which numbers reach the renderer at all.
 *
 * @internal
 */
#[CoversClass(ProblemDetailsResponseFactory::class)]
final class ProblemDetailsResponseFactoryTest extends TestCase
{
    public function testADebugPageCarriesTheRequestContextProfilerCounters(): void
    {
        $errorRequestContext = new ErrorRequestContext('ctx-trace-1');
        for ($i = 0; $i < 7; ++$i) {
            $errorRequestContext->recordQuery();
        }

        $errorRequestContext->recordException();
        $errorRequestContext->recordException();

        // A two-millisecond floor guarantees the toolbar renders a duration
        // the branch-swap mutants cannot answer with their zeroed default.
        usleep(2000);

        $serverRequest = $this->htmlRequest()->withAttribute(
            ErrorRequestContext::ATTRIBUTE,
            $errorRequestContext,
        );

        $body = (string) ProblemDetailsResponseFactory::create(
            new RuntimeException('boom'),
            500,
            $serverRequest,
            'req-123',
            true,
        )->getBody();

        self::assertStringContainsString('<span>DB queries: 7</span>', $body);
        self::assertStringContainsString('<span>Exceptions: 2</span>', $body);
        self::assertSame(1, preg_match('/Duration: (\d+\.\d{2}) ms/', $body, $duration));
        self::assertGreaterThanOrEqual(1.0, (float) $duration[1]);
    }

    public function testADebugPageWithoutARequestContextDefaultsTheProfilerCounters(): void
    {
        $body = (string) ProblemDetailsResponseFactory::create(
            new RuntimeException('boom'),
            500,
            $this->htmlRequest(),
            'req-123',
            true,
        )->getBody();

        self::assertStringContainsString('<span>Duration: 0.00 ms</span>', $body);
        self::assertStringContainsString('<span>DB queries: 0</span>', $body);
        self::assertStringContainsString('<span>Exceptions: 1</span>', $body);
    }

    private function htmlRequest(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://app.example/orders'), [], [], [], [], null, [
            'Accept' => 'text/html',
        ]);
    }
}
