<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HeaderAppender;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\HeaderIndex;
use Zef\Framework\Http\HeaderStore;
use Zef\Framework\Http\HeaderTable;
use Zef\Framework\Http\HostLine;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpMethod;
use Zef\Framework\Http\HttpVersion;
use Zef\Framework\Http\Message;
use Zef\Framework\Http\Request;
use Zef\Framework\Http\RequestTarget;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Framework\Http\UriHostPolicy;

/**
 * @internal
 */
#[CoversClass(Request::class)]
#[CoversClass(Message::class)]
#[CoversClass(HeaderStore::class)]
#[CoversClass(HeaderTable::class)]
#[CoversClass(HeaderAppender::class)]
#[CoversClass(HeaderIndex::class)]
#[CoversClass(HeaderGrammar::class)]
#[CoversClass(HttpMethod::class)]
#[CoversClass(RequestTarget::class)]
#[CoversClass(HostLine::class)]
#[CoversClass(UriHostPolicy::class)]
#[CoversClass(HttpVersion::class)]
final class RequestTest extends TestCase
{
    public function testDefaultsToTheOriginFormRootTargetAndAnEmptyBody(): void
    {
        $uri = new Uri();
        $request = new Request('GET', $uri);

        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('GET', $request->getMethod());
        self::assertSame($uri, $request->getUri());
        self::assertSame('', $request->getBody()->getContents());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame([], $request->getHeaders());
    }

    public function testDerivesTheOriginFormTargetFromTheUri(): void
    {
        self::assertSame('/page', new Request('GET', new Uri('/page'))->getRequestTarget());
        self::assertSame('/page?a=1', new Request('GET', new Uri('/page?a=1'))->getRequestTarget());
        self::assertSame('/', new Request('GET', new Uri('http://example.com'))->getRequestTarget());
        self::assertSame('/page', new Request('GET', new Uri('page'))->getRequestTarget());
        self::assertSame('/?only=query', new Request('GET', new Uri('?only=query'))->getRequestTarget());
    }

    public function testAnExplicitTargetWinsOverTheDerivation(): void
    {
        $request = new Request('OPTIONS', new Uri('http://example.com/page'), requestTarget: '*');

        self::assertSame('*', $request->getRequestTarget());

        $absolute = new Request('GET', new Uri('http://example.com/page'), requestTarget: 'http://example.com/page');

        self::assertSame('http://example.com/page', $absolute->getRequestTarget());

        $retargeted = new Request('GET', new Uri('/derived'))->withRequestTarget('/other?x=1');

        self::assertSame('/other?x=1', $retargeted->getRequestTarget());
    }

    public function testAnExplicitTargetSurvivesTheRequestLineWithers(): void
    {
        $request = new Request('OPTIONS', new Uri('http://example.com/page'), requestTarget: '*');
        $remethoded = $request->withMethod('POST');
        $redirected = $remethoded->withUri(new Uri('http://other.example/other'));

        self::assertSame('*', $remethoded->getRequestTarget());
        self::assertSame('*', $redirected->getRequestTarget());
    }

    public function testRejectsControlCharactersInTheTarget(): void
    {
        $this->assertThrows('Invalid request target.', static function (): void {
            new Request('GET', new Uri('/'), requestTarget: "/bad\r\nHost: evil");
        });
        $this->assertThrows('Invalid request target.', static function (): void {
            new Request('GET', new Uri('/'))->withRequestTarget("/bad\r\nHost: evil");
        });
        $this->assertThrows('Invalid request target.', static function (): void {
            new Request('GET', new Uri('/'))->withRequestTarget("/tab\ttarget");
        });
        $this->assertThrows('Invalid request target.', static function (): void {
            new Request('GET', new Uri('/'))->withRequestTarget("/nu\0l");
        });
        $this->assertThrows('Invalid request target.', static function (): void {
            new Request('GET', new Uri('/'))->withRequestTarget("/de\x7Flete");
        });
    }

    public function testAcceptsObsTextInTheTarget(): void
    {
        $request = new Request('GET', new Uri('/'))->withRequestTarget("/caf\xc3\xa9");

        self::assertSame("/caf\xc3\xa9", $request->getRequestTarget());
    }

    public function testAcceptsRegisteredAndExtensionMethods(): void
    {
        foreach (['GET', 'POST', 'PROPFIND', 'X-CUSTOM-1'] as $method) {
            self::assertSame($method, new Request($method, new Uri('/'))->getMethod());
        }

        $remethoded = new Request('GET', new Uri('/'))->withMethod('PATCH');

        self::assertSame('PATCH', $remethoded->getMethod());
    }

    public function testRejectsMethodsOutsideTheTokenGrammar(): void
    {
        $this->assertThrows("Invalid HTTP method ''.", static function (): void {
            new Request('', new Uri('/'));
        });
        $this->assertThrows("Invalid HTTP method 'G T'.", static function (): void {
            new Request('G T', new Uri('/'));
        });
        $this->assertThrows("Invalid HTTP method 'G€T'.", static function (): void {
            new Request('G€T', new Uri('/'));
        });
        $this->assertThrows("Invalid HTTP method 'GET;'.", static function (): void {
            new Request('GET', new Uri('/'))->withMethod('GET;');
        });
        $this->assertThrows("Invalid HTTP method 'GET\n'.", static function (): void {
            new Request("GET\n", new Uri('/'));
        });
    }

    public function testBootstrapsTheHostHeaderFromTheUri(): void
    {
        $request = new Request('GET', new Uri('https://example.com/page'));

        self::assertTrue($request->hasHeader('Host'));
        self::assertSame(['example.com'], $request->getHeader('Host'));
        self::assertSame('example.com', $request->getHeaderLine('Host'));
    }

    public function testLeavesAnExplicitHostHeaderAlone(): void
    {
        $request = new Request('GET', new Uri('http://example.com/page'), ['Host' => 'explicit.example']);

        self::assertSame('explicit.example', $request->getHeaderLine('Host'));
    }

    public function testRecognizesAnExplicitHostHeaderCaseInsensitively(): void
    {
        $request = new Request('GET', new Uri('http://example.com/page'), ['host' => 'explicit.example']);

        self::assertSame('explicit.example', $request->getHeaderLine('Host'));
        self::assertSame(['host' => ['explicit.example']], $request->getHeaders());
    }

    public function testBracketsIpv6HostsAndKeepsNonDefaultPorts(): void
    {
        $request = new Request('GET', new Uri('http://[::1]:8080/x'));

        self::assertSame('[::1]:8080', $request->getHeaderLine('Host'));
    }

    public function testOmitsDefaultPortsFromTheHostLine(): void
    {
        self::assertSame('example.com', new Request('GET', new Uri('http://example.com:80/'))->getHeaderLine('Host'));
        self::assertSame('example.com', new Request('GET', new Uri('https://example.com:443/'))->getHeaderLine('Host'));
    }

    public function testSkipsTheHostHeaderForHostlessUris(): void
    {
        $request = new Request('GET', new Uri('/path'));

        self::assertFalse($request->hasHeader('Host'));
        self::assertSame('', $request->getHeaderLine('Host'));
    }

    public function testWithUriRefreshesTheHostHeaderByDefault(): void
    {
        $request = new Request('GET', new Uri('http://example.com/page'));
        $redirected = $request->withUri(new Uri('http://other.example:8080/other'));

        self::assertSame('other.example:8080', $redirected->getHeaderLine('Host'));
        self::assertSame('/other', $redirected->getUri()->getPath());
        self::assertSame('/other', $redirected->getRequestTarget());
    }

    public function testWithUriHonoursPreserveHostWhenAHostHeaderExists(): void
    {
        $request = new Request('GET', new Uri('http://example.com/page'));
        $redirected = $request->withUri(new Uri('http://other.example/other'), true);

        self::assertSame('example.com', $redirected->getHeaderLine('Host'));
        self::assertSame('/other', $redirected->getUri()->getPath());
    }

    public function testWithUriAddsTheHostHeaderWhenPreserveHostIsSetAndNoneExists(): void
    {
        $request = new Request('GET', new Uri('/path'));
        $redirected = $request->withUri(new Uri('http://added.example:8080/'), true);

        self::assertSame('added.example:8080', $redirected->getHeaderLine('Host'));
    }

    public function testWithUriKeepsTheHostHeaderForAHostlessUri(): void
    {
        $request = new Request('GET', new Uri('http://example.com/page'));
        $redirected = $request->withUri(new Uri('/other'));

        self::assertSame('example.com', $redirected->getHeaderLine('Host'));
        self::assertSame('/other', $redirected->getUri()->getPath());
    }

    public function testWithUriPreservesTheMessageHalf(): void
    {
        $request = new Request(
            'GET',
            new Uri('http://example.com/page'),
            ['X-Foo' => 'a'],
            Stream::fromString('body'),
            '2',
        );
        $redirected = $request->withUri(new Uri('http://other.example/other'));

        self::assertSame('2', $redirected->getProtocolVersion());
        self::assertSame(['a'], $redirected->getHeader('X-Foo'));
        self::assertSame('body', $redirected->getBody()->getContents());
        self::assertSame('GET', $redirected->getMethod());
    }

    public function testPassesHeadersThroughToTheMessageHalf(): void
    {
        $request = new Request('GET', new Uri('/'), ['X-Foo' => 'a', 'X-Bar' => ['c', 'd']]);

        self::assertSame(['X-Foo' => ['a'], 'X-Bar' => ['c', 'd']], $request->getHeaders());
    }

    public function testBuildsABodyFromTheDefaultAndAcceptsAStream(): void
    {
        $stream = Stream::fromString('payload');

        self::assertSame('', new Request('GET', new Uri('/'))->getBody()->getContents());
        self::assertSame('payload', new Request('POST', new Uri('/'), stream: $stream)->getBody()->getContents());
    }

    public function testRejectsInvalidProtocolVersions(): void
    {
        $this->assertThrows('Invalid HTTP protocol version.', static function (): void {
            new Request('GET', new Uri('/'), protocolVersion: '1.2.3');
        });
        $this->assertThrows('Invalid HTTP protocol version.', static function (): void {
            new Request('GET', new Uri('/'))->withProtocolVersion('v2');
        });
        $this->assertThrows('Invalid HTTP protocol version.', static function (): void {
            new Request('GET', new Uri('/'))->withProtocolVersion("1.1\n");
        });
    }

    public function testRejectsInvalidHeadersAtConstruction(): void
    {
        $this->assertThrows("Invalid header name 'X Foo'.", static function (): void {
            new Request('GET', new Uri('/'), ['X Foo' => 'v']);
        });
    }

    public function testValidatesTheMessageHalfBeforeTheRequestLine(): void
    {
        $this->assertThrows("Invalid header name 'X Foo'.", static function (): void {
            new Request('G T', new Uri('/'), ['X Foo' => 'v']);
        });
    }

    public function testEveryWitherLeavesTheOriginalUntouched(): void
    {
        $request = new Request(
            'GET',
            new Uri('http://example.com/page?a=1'),
            ['X-Foo' => 'a'],
            Stream::fromString('body'),
        );

        self::assertSame('*', $request->withRequestTarget('*')->getRequestTarget());
        self::assertSame('/page?a=1', $request->getRequestTarget());
        self::assertSame('POST', $request->withMethod('POST')->getMethod());
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'other.example',
            $request->withUri(new Uri('http://other.example/other'))->getHeaderLine('Host'),
        );
        self::assertSame('example.com', $request->getHeaderLine('Host'));
        self::assertSame('/other', $request->withUri(new Uri('http://other.example/other'))->getUri()->getPath());
        self::assertSame('/page', $request->getUri()->getPath());
        self::assertSame('1.0', $request->withProtocolVersion('1.0')->getProtocolVersion());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame(['b'], $request->withHeader('X-Foo', 'b')->getHeader('X-Foo'));
        self::assertSame(['a'], $request->getHeader('X-Foo'));
        self::assertSame(['a', 'b'], $request->withAddedHeader('X-Foo', 'b')->getHeader('X-Foo'));
        self::assertSame(['a'], $request->getHeader('X-Foo'));
        self::assertSame([], $request->withoutHeader('X-Foo')->getHeader('X-Foo'));
        self::assertSame(['a'], $request->getHeader('X-Foo'));
        self::assertSame('swapped', $request->withBody(Stream::fromString('swapped'))->getBody()->getContents());
        self::assertSame('body', $request->getBody()->getContents());
    }

    /**
     * @param callable(): void $operation
     */
    private function assertThrows(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
