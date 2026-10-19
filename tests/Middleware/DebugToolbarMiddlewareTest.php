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
use Zef\Framework\Http\StreamSnapshot;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\DebugToolbarBodySplice;
use Zef\Framework\Middleware\DebugToolbarEligibility;
use Zef\Framework\Middleware\DebugToolbarFactory;
use Zef\Framework\Middleware\DebugToolbarInjector;
use Zef\Framework\Middleware\DebugToolbarMarkup;
use Zef\Framework\Middleware\DebugToolbarMiddleware;
use Zef\Test\Support\Http\FixedResponseHandler;

/** @internal */
#[CoversClass(DebugToolbarFactory::class)]
#[CoversClass(DebugToolbarMiddleware::class)]
#[CoversClass(DebugToolbarEligibility::class)]
#[CoversClass(DebugToolbarInjector::class)]
#[CoversClass(DebugToolbarMarkup::class)]
#[CoversClass(DebugToolbarBodySplice::class)]
#[CoversClass(StreamSnapshot::class)]
#[CoversClass(ErrorRequestContext::class)]
final class DebugToolbarMiddlewareTest extends TestCase
{
    public function testAddsToolbarAndRequestMetricsToSmallHtmlResponses(): void
    {
        $errorRequestContext = new ErrorRequestContext('trace-toolbar');
        $errorRequestContext->recordQuery();
        $errorRequestContext->recordQuery();

        $request = $this->request('text/html')->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext);

        $response = new DebugToolbarMiddleware(true)->process(
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

    public function testPreservesExistingContentSecurityPolicyWithoutInjectingToolbar(): void
    {
        $policy = "default-src 'self'; script-src 'self'";
        $response = new Response(
            200,
            ['Content-Type' => 'text/html', 'Content-Security-Policy' => $policy],
            '<html><body>application</body></html>',
        );

        $actual = new DebugToolbarMiddleware(true)->process(
            $this->request('text/html'),
            new FixedResponseHandler($response),
        );

        self::assertSame($policy, $actual->getHeaderLine('Content-Security-Policy'));
        self::assertSame('<html><body>application</body></html>', (string) $actual->getBody());
        self::assertSame('', $actual->getHeaderLine('X-Debug-Toolbar'));
    }

    public function testDoesNotModifyNonHtmlOrNonSuccessfulResponses(): void
    {
        $debugToolbarMiddleware = new DebugToolbarMiddleware(true);
        $request = $this->request('application/json');
        $json = new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}');
        $jsonResponse = $debugToolbarMiddleware->process($request, new FixedResponseHandler($json));
        self::assertSame('{"ok":true}', (string) $jsonResponse->getBody());
        self::assertSame('', $jsonResponse->getHeaderLine('X-Debug-Toolbar'));

        $htmlRequest = $this->request('text/html');
        $error = new Response(500, ['Content-Type' => 'text/html'], '<html><body>error</body></html>');
        $errorResponse = $debugToolbarMiddleware->process($htmlRequest, new FixedResponseHandler($error));
        self::assertSame('<html><body>error</body></html>', (string) $errorResponse->getBody());
        self::assertSame('', $errorResponse->getHeaderLine('X-Debug-Toolbar'));
    }

    public function testDoesNotModifyHeadCompressedLargeOrUnclosedBodies(): void
    {
        $debugToolbarMiddleware = new DebugToolbarMiddleware(true);
        $head = $this->request('text/html')->withMethod('HEAD');
        self::assertInstanceOf(ServerRequestInterface::class, $head);
        $headResponse = $debugToolbarMiddleware->process($head, new FixedResponseHandler(new Response(
            200,
            ['Content-Type' => 'text/html'],
            '<html><body>head</body></html>',
        )));
        self::assertSame('', $headResponse->getHeaderLine('X-Debug-Toolbar'));

        $compressed = $debugToolbarMiddleware->process(
            $this->request('text/html'),
            new FixedResponseHandler(new Response(
                200,
                ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip'],
                '<html><body>compressed</body></html>',
            ))
        );
        self::assertSame('', $compressed->getHeaderLine('X-Debug-Toolbar'));

        $large = $debugToolbarMiddleware->process($this->request('text/html'), new FixedResponseHandler(new Response(
            200,
            ['Content-Type' => 'text/html'],
            str_repeat('x', 2_000_001),
        )));
        self::assertSame('', $large->getHeaderLine('X-Debug-Toolbar'));

        $unclosed = $debugToolbarMiddleware->process($this->request('text/html'), new FixedResponseHandler(new Response(
            200,
            ['Content-Type' => 'text/html'],
            '<html><body>missing close',
        )));
        self::assertSame('', $unclosed->getHeaderLine('X-Debug-Toolbar'));
    }

    public function testPreservesAnExistingContentSecurityPolicyAndSkipsToolbarInjection(): void
    {
        $policy = "default-src 'self'; script-src 'self'";

        $response = new DebugToolbarMiddleware(true)->process(
            $this->request('text/html'),
            new FixedResponseHandler(new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8', 'Content-Security-Policy' => $policy],
                '<html><body>application page</body></html>',
            )),
        );

        self::assertSame($policy, $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('<html><body>application page</body></html>', (string) $response->getBody());
        self::assertSame('', $response->getHeaderLine('X-Debug-Toolbar'));
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
                new FixedResponseHandler(new Response(
                    200,
                    ['Content-Type' => 'text/html'],
                    '<html><body>x</body></html>',
                )),
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
        $request = new ServerRequest('GET', new Uri('https://app.example.test/'))
            ->withHeader('Accept', $accept)
        ;
        self::assertInstanceOf(ServerRequest::class, $request);

        return $request;
    }
}
