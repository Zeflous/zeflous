<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\ErrorContentNegotiator;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ErrorTraceIdResolver;
use Zef\Framework\Http\HtmlErrorPageContext;
use Zef\Framework\Http\HtmlErrorPageRenderer;
use Zef\Framework\Http\ProblemDetailsResponseFactory;
use Zef\Framework\Http\ProblemDetailsResponseHeaders;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

/**
 * The browser-facing factory's profiler bridge: the request-scoped
 * ErrorRequestContext facts (duration, DB queries, exceptions) must
 * reach the debug toolbar exactly, and their absence must fall back to
 * the flat defaults -- the two directions every instanceof ternary on
 * that bridge can be rewritten in.
 *
 * @internal
 */
#[CoversClass(ProblemDetailsResponseFactory::class)]
#[CoversClass(HtmlErrorPageRenderer::class)]
#[CoversClass(HtmlErrorPageContext::class)]
#[CoversClass(ErrorContentNegotiator::class)]
#[CoversClass(ErrorRequestContext::class)]
#[CoversClass(ErrorTraceIdResolver::class)]
#[CoversClass(ProblemDetailsResponseHeaders::class)]
final class ProblemDetailsResponseFactoryTest extends TestCase
{
    public function testTheDebugPageCarriesTheRequestContextProfilerFacts(): void
    {
        $errorRequestContext = new ErrorRequestContext('req-factory-1');
        $errorRequestContext->recordQuery();
        $errorRequestContext->recordQuery();
        $errorRequestContext->recordQuery();
        $errorRequestContext->recordException();
        $errorRequestContext->recordException();
        usleep(2500);

        $response = ProblemDetailsResponseFactory::create(
            new RuntimeException('factory bridge'),
            500,
            $this->browserRequest()->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext),
            'req-factory-1',
            true,
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('DB queries: 3', $body);
        self::assertStringContainsString('Exceptions: 2', $body);
        self::assertStringNotContainsString('Duration: 0.00 ms', $body);
        self::assertMatchesRegularExpression('/Duration: [1-9]\d*\.\d{2} ms/D', $body);
    }

    public function testTheDebugPageFallsBackToFlatDefaultsWithoutARequestContext(): void
    {
        $response = ProblemDetailsResponseFactory::create(
            new RuntimeException('factory defaults'),
            500,
            $this->browserRequest(),
            'req-factory-2',
            true,
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('Duration: 0.00 ms', $body);
        self::assertStringContainsString('DB queries: 0', $body);
        self::assertStringContainsString('Exceptions: 1', $body);
    }

    private function browserRequest(): ServerRequest
    {
        $request = new ServerRequest('GET', new Uri('https://app.example.test/broken'))
            ->withHeader('Accept', 'text/html')
        ;
        self::assertInstanceOf(ServerRequest::class, $request);

        return $request;
    }
}
