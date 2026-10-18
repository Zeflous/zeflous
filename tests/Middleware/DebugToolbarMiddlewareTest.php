<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\DebugToolbarFactory;
use Zef\Framework\Middleware\DebugToolbarMiddleware;
use Zef\Test\Support\Http\FixedResponseHandler;

/** @internal */
#[CoversClass(DebugToolbarFactory::class)]
#[CoversClass(DebugToolbarMiddleware::class)]
#[CoversClass(ErrorRequestContext::class)]
final class DebugToolbarMiddlewareTest extends TestCase
{
    public function testAddsToolbarAndRequestMetricsToSmallHtmlResponses(): void
    {
        $context = new ErrorRequestContext('trace-toolbar');
        $context->recordQuery();
        $context->recordQuery();
        $request = $this->request('text/html')->withAttribute(ErrorRequestContext::ATTRIBUTE, $context);
        self::assertInstanceOf(ServerRequestInterface::class, $request);

        $response = (new DebugToolbarMiddleware(true))->process(
            $request,
            new FixedResponseHandler(new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8', 'Content-Length' => '44', 'ETag' => '"original"'],
                '<!doctype html><html><body>hello</body></html>',
            )),
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('ZEF Profiler', $body);
        self::assertStringContainsString('DB queries: 2', $body);
        self::assertStringContainsString('Exceptions: 0', $body);
        self::assertStringContainsString('Trace: trace-toolbar', $body);
        self::assertStringContainsString('</aside></body>', $body);
        self::assertSame('enabled', $response->getHeaderLine('X-Debug-Toolbar'));
        self::assertSame('', $response->getHeaderLine('Content-Length'));
        self::assertSame('', $response->getHeaderLine('ETag'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testDoesNotModifyNonHtmlOrNonSuccessfulResponses(): void
    {
        $middleware = new DebugToolbarMiddleware(true);
        $request = $this->request('application/json');
        $json = new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}');
        $jsonResponse = $middleware->process($request, new FixedResponseHandler($json));
        self::assertSame('{"ok":true}', (string) $jsonResponse->getBody());
        self::assertSame('', $jsonResponse->getHeaderLine('X-Debug-Toolbar'));

        $htmlRequest = $this->request('text/html');
        $error = new Response(500, ['Content-Type' => 'text/html'], '<html><body>error</body></html>');
        $errorResponse = $middleware->process($htmlRequest, new FixedResponseHandler($error));
        self::assertSame('<html><body>error</body></html>', (string) $errorResponse->getBody());
        self::assertSame('', $errorResponse->getHeaderLine('X-Debug-Toolbar'));
    }

    public function testDoesNotModifyHeadCompressedLargeOrUnclosedBodies(): void
    {
        $middleware = new DebugToolbarMiddleware(true);
        $head = $this->request('text/html')->withMethod('HEAD');
        $headResponse = $middleware->process($head, new FixedResponseHandler(new Response(
            200,
            ['Content-Type' => 'text/html'],
            '<html><body>head</body></html>',
        )));
        self::assertSame('', $headResponse->getHeaderLine('X-Debug-Toolbar'));

        $compressed = $middleware->process($this->request('text/html'), new FixedResponseHandler(new Response(
            200,
            ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip'],
            '<html><body>compressed</body></html>',
        )));
        self::assertSame('', $compressed->getHeaderLine('X-Debug-Toolbar'));

        $large = $middleware->process($this->request('text/html'), new FixedResponseHandler(new Response(
            200,
            ['Content-Type' => 'text/html'],
            str_repeat('x', 2_000_001),
        )));
        self::assertSame('', $large->getHeaderLine('X-Debug-Toolbar'));

        $unclosed = $middleware->process($this->request('text/html'), new FixedResponseHandler(new Response(
            200,
            ['Content-Type' => 'text/html'],
            '<html><body>missing close',
        )));
        self::assertSame('', $unclosed->getHeaderLine('X-Debug-Toolbar'));
    }

    public function testFactoryRequiresDebugAndLocalEnvironment(): void
    {
        $previous = getenv('APP_ENV');
        try {
            putenv('APP_ENV=local');
            $enabled = DebugToolbarFactory::fromConfig(new Config([
                'http' => ['error_handling' => ['debug' => true]],
            ]))->process(
                $this->request('text/html'),
                new FixedResponseHandler(new Response(
                    200,
                    ['Content-Type' => 'text/html'],
                    '<html><body>x</body></html>',
                )),
            );
            self::assertSame('enabled', $enabled->getHeaderLine('X-Debug-Toolbar'));

            putenv('APP_ENV=production');
            $disabled = DebugToolbarFactory::fromConfig(new Config([
                'http' => ['error_handling' => ['debug' => true]],
            ]))->process(
                $this->request('text/html'),
                new FixedResponseHandler(new Response(200, ['Content-Type' => 'text/html'], '<html><body>x</body></html>')),
            );
            self::assertSame('', $disabled->getHeaderLine('X-Debug-Toolbar'));
        } finally {
            if ($previous === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $previous);
            }
        }
    }

    public function testFactoryRejectsInvalidDebugConfiguration(): void
    {
        $this->expectException(ConfigException::class);
        DebugToolbarFactory::fromConfig(new Config([
            'http' => ['error_handling' => ['debug' => 'yes']],
        ]));
    }

    private function request(string $accept): ServerRequest
    {
        $request = (new ServerRequest('GET', new Uri('https://app.example.test/')))
            ->withHeader('Accept', $accept);
        self::assertInstanceOf(ServerRequest::class, $request);

        return $request;
    }
}
