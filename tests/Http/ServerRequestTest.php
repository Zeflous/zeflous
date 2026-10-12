<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\HeaderIndex;
use Zef\Framework\Http\HeaderStore;
use Zef\Framework\Http\HeaderTable;
use Zef\Framework\Http\HostLine;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpMethod;
use Zef\Framework\Http\HttpVersion;
use Zef\Framework\Http\Message;
use Zef\Framework\Http\ParsedBody;
use Zef\Framework\Http\Request;
use Zef\Framework\Http\RequestTarget;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\UploadedTree;
use Zef\Framework\Http\Uri;
use Zef\Framework\Http\UriHostPolicy;
use Zef\Test\Support\Http\UploadedFileDouble;

/**
 * @internal
 */
#[CoversClass(ServerRequest::class)]
#[CoversClass(Request::class)]
#[CoversClass(ParsedBody::class)]
#[CoversClass(UploadedTree::class)]
#[CoversClass(UriHostPolicy::class)]
#[CoversClass(Message::class)]
#[CoversClass(HeaderStore::class)]
#[CoversClass(HeaderTable::class)]
#[CoversClass(HeaderIndex::class)]
#[CoversClass(HeaderGrammar::class)]
#[CoversClass(HttpMethod::class)]
#[CoversClass(RequestTarget::class)]
#[CoversClass(HostLine::class)]
#[CoversClass(HttpVersion::class)]
final class ServerRequestTest extends TestCase
{
    public function testDefaultsToEmptyBagsAndANullParsedBody(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('/'));

        self::assertSame([], $serverRequest->getServerParams());
        self::assertSame([], $serverRequest->getCookieParams());
        self::assertSame([], $serverRequest->getQueryParams());
        self::assertSame([], $serverRequest->getUploadedFiles());
        self::assertNull($serverRequest->getParsedBody());
        self::assertSame([], $serverRequest->getAttributes());
        self::assertSame('/', $serverRequest->getRequestTarget());
        self::assertSame('GET', $serverRequest->getMethod());
    }

    public function testCarriesEveryBagFromConstruction(): void
    {
        $uploadedFileDouble = new UploadedFileDouble();
        $stream = Stream::fromString('payload');
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('http://example.com/page?a=1'),
            ['REQUEST_METHOD' => 'POST'],
            ['session' => 'abc'],
            ['page' => '2'],
            ['avatar' => $uploadedFileDouble],
            ['title' => 'x'],
            ['X-Foo' => 'a'],
            $stream,
            '2',
            '*',
            ['route' => 'user.show'],
        );

        self::assertSame(['REQUEST_METHOD' => 'POST'], $serverRequest->getServerParams());
        self::assertSame(['session' => 'abc'], $serverRequest->getCookieParams());
        self::assertSame(['page' => '2'], $serverRequest->getQueryParams());
        self::assertSame(['avatar' => $uploadedFileDouble], $serverRequest->getUploadedFiles());
        self::assertSame($uploadedFileDouble, $serverRequest->getUploadedFiles()['avatar']);
        self::assertSame(['title' => 'x'], $serverRequest->getParsedBody());
        self::assertSame(['route' => 'user.show'], $serverRequest->getAttributes());
        self::assertSame(['a'], $serverRequest->getHeader('X-Foo'));
        self::assertSame('example.com', $serverRequest->getHeaderLine('Host'));
        self::assertSame('payload', $serverRequest->getBody()->getContents());
        self::assertSame($stream, $serverRequest->getBody());
        self::assertSame('2', $serverRequest->getProtocolVersion());
        self::assertSame('*', $serverRequest->getRequestTarget());
        self::assertSame('POST', $serverRequest->getMethod());
    }

    public function testAcceptsAnArrayObjectAndNullParsedBody(): void
    {
        $object = new stdClass();

        self::assertSame(['a' => 1], new ServerRequest('POST', new Uri('/'), parsedBody: ['a' => 1])->getParsedBody());
        self::assertSame($object, new ServerRequest('POST', new Uri('/'), parsedBody: $object)->getParsedBody());
        self::assertNull(new ServerRequest('POST', new Uri('/'))->getParsedBody());
        self::assertSame(
            ['a' => 2],
            new ServerRequest('POST', new Uri('/'), parsedBody: $object)->withParsedBody(['a' => 2])->getParsedBody(),
        );
    }

    public function testRejectsScalarParsedBodies(): void
    {
        $this->assertThrows('Parsed body must be array, object or null.', static function (): void {
            new ServerRequest('POST', new Uri('/'), parsedBody: 'not-an-array');
        });
        $this->assertThrows('Parsed body must be array, object or null.', static function (): void {
            new ServerRequest('POST', new Uri('/'), parsedBody: 42);
        });
        $this->assertThrows('Parsed body must be array, object or null.', static function (): void {
            new ServerRequest('POST', new Uri('/'), parsedBody: 3.14);
        });
        $this->assertThrows('Parsed body must be array, object or null.', static function (): void {
            new ServerRequest('POST', new Uri('/'), parsedBody: false);
        });
        $this->assertThrows('Parsed body must be array, object or null.', static function (): void {
            new ServerRequest('POST', new Uri('/'))->withParsedBody('not-an-array');
        });
    }

    public function testValidatesTheParsedBodyBeforeTheMessageHalf(): void
    {
        $this->assertThrows('Parsed body must be array, object or null.', static function (): void {
            new ServerRequest('POST', new Uri('/'), parsedBody: 'x', headers: ['X Foo' => 'v']);
        });
    }

    public function testValidatesTheRequestLineBeforeTheUploadTree(): void
    {
        $this->assertThrows("Invalid HTTP method 'G T'.", static function (): void {
            new ServerRequest('G T', new Uri('/'), uploadedFiles: ['broken' => 'nope']);
        });
    }

    public function testValidatesTheUploadTreeOnConstruction(): void
    {
        $this->assertThrows('Uploaded files must contain only UploadedFileInterface leaves.', static function (): void {
            new ServerRequest('POST', new Uri('/'), uploadedFiles: ['broken' => 'nope']);
        });
    }

    public function testAcceptsNestedUploadTrees(): void
    {
        $uploadedFileDouble = new UploadedFileDouble();
        $serverRequest = new ServerRequest('POST', new Uri('/'));

        $nested = $serverRequest->withUploadedFiles(['nested' => ['f' => $uploadedFileDouble]]);
        $tree = $nested->getUploadedFiles();

        self::assertArrayHasKey('nested', $tree);
        self::assertIsArray($tree['nested']);
        self::assertSame($uploadedFileDouble, $tree['nested']['f']);

        $deeper = $serverRequest->withUploadedFiles(['a' => ['b' => ['c' => $uploadedFileDouble]]]);

        self::assertSame(['a' => ['b' => ['c' => $uploadedFileDouble]]], $deeper->getUploadedFiles());
    }

    public function testRejectsInvalidLeavesAnywhereInTheTree(): void
    {
        $this->assertThrows('Uploaded files must contain only UploadedFileInterface leaves.', static function (): void {
            new ServerRequest('POST', new Uri('/'))->withUploadedFiles(['broken' => 'nope']);
        });
        $this->assertThrows('Uploaded files must contain only UploadedFileInterface leaves.', static function (): void {
            new ServerRequest('POST', new Uri('/'))->withUploadedFiles(['a' => ['b' => 'nope']]);
        });
        $this->assertThrows('Uploaded files must contain only UploadedFileInterface leaves.', static function (): void {
            new ServerRequest('POST', new Uri('/'))->withUploadedFiles(['not-an-upload' => new stdClass()]);
        });
        $this->assertThrows('Uploaded files must contain only UploadedFileInterface leaves.', static function (): void {
            new ServerRequest('POST', new Uri('/'))->withUploadedFiles(['ok' => new UploadedFileDouble(), 'bad' => 5]);
        });
    }

    public function testAFailedUploadWitherLeavesTheOriginalUntouched(): void
    {
        $uploadedFileDouble = new UploadedFileDouble();
        $serverRequest = new ServerRequest('POST', new Uri('/'), uploadedFiles: ['avatar' => $uploadedFileDouble]);

        try {
            $serverRequest->withUploadedFiles(['broken' => 'nope']);
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'Uploaded files must contain only UploadedFileInterface leaves.',
                $httpException->getMessage(),
            );
        }

        self::assertSame(['avatar' => $uploadedFileDouble], $serverRequest->getUploadedFiles());
    }

    public function testWithCookieParamsSwapsTheBag(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('/'), cookieParams: ['session' => 'abc']);

        $recookied = $serverRequest->withCookieParams(['other' => 'xyz']);

        self::assertSame(['other' => 'xyz'], $recookied->getCookieParams());
        self::assertSame(['session' => 'abc'], $serverRequest->getCookieParams());
    }

    public function testWithQueryParamsSwapsTheBag(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('/?a=1'), queryParams: ['a' => '1']);

        $requeried = $serverRequest->withQueryParams(['b' => '2']);

        self::assertSame(['b' => '2'], $requeried->getQueryParams());
        self::assertSame(['a' => '1'], $serverRequest->getQueryParams());
    }

    public function testAttributesRoundTrip(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('/'));

        self::assertNull($serverRequest->getAttribute('missing'));
        self::assertSame('fallback', $serverRequest->getAttribute('missing', 'fallback'));

        $attributed = $serverRequest->withAttribute('k', 'v');

        self::assertSame('v', $attributed->getAttribute('k'));
        self::assertSame(['k' => 'v'], $attributed->getAttributes());

        $overwritten = $attributed->withAttribute('k', 'v2');

        self::assertSame('v2', $overwritten->getAttribute('k'));
        self::assertSame(['k' => 'v2'], $overwritten->getAttributes());

        $pruned = $attributed->withoutAttribute('k');

        self::assertNull($pruned->getAttribute('k'));
        self::assertSame([], $pruned->getAttributes());
        self::assertSame(['k' => 'v'], $attributed->getAttributes());
    }

    public function testWithoutAttributeToleratesAbsence(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('/'), attributes: ['k' => 'v']);

        $pruned = $serverRequest->withoutAttribute('absent');

        self::assertSame(['k' => 'v'], $pruned->getAttributes());
    }

    public function testInheritsTheRequestLineSemantics(): void
    {
        $serverRequest = new ServerRequest('POST', new Uri('http://example.com/page?a=1'), requestTarget: '*');

        self::assertSame('*', $serverRequest->getRequestTarget());
        self::assertSame('example.com', $serverRequest->getHeaderLine('Host'));

        $remethoded = $serverRequest->withMethod('PUT');

        self::assertSame('PUT', $remethoded->getMethod());
        self::assertSame('*', $remethoded->getRequestTarget());
    }

    public function testRequestLineWithersKeepTheServerHalf(): void
    {
        $uploadedFileDouble = new UploadedFileDouble();
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('http://example.com/page'),
            ['REQUEST_METHOD' => 'POST'],
            ['session' => 'abc'],
            ['page' => '2'],
            ['avatar' => $uploadedFileDouble],
            ['title' => 'x'],
        );

        $redirected = $serverRequest->withUri(new Uri('http://other.example/other'));
        self::assertInstanceOf(ServerRequest::class, $redirected);

        self::assertSame('other.example', $redirected->getHeaderLine('Host'));
        self::assertSame('/other', $redirected->getUri()->getPath());
        self::assertSame(['REQUEST_METHOD' => 'POST'], $redirected->getServerParams());
        self::assertSame(['session' => 'abc'], $redirected->getCookieParams());
        self::assertSame(['page' => '2'], $redirected->getQueryParams());
        self::assertSame(['avatar' => $uploadedFileDouble], $redirected->getUploadedFiles());
        self::assertSame(['title' => 'x'], $redirected->getParsedBody());

        $retargeted = $serverRequest->withRequestTarget('*');
        self::assertInstanceOf(ServerRequest::class, $retargeted);

        self::assertSame('*', $retargeted->getRequestTarget());
        self::assertSame(['session' => 'abc'], $retargeted->getCookieParams());
    }

    public function testEveryWitherLeavesTheOriginalUntouched(): void
    {
        $uploadedFileDouble = new UploadedFileDouble();
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('http://example.com/page?a=1'),
            ['REQUEST_METHOD' => 'POST'],
            ['session' => 'abc'],
            ['page' => '2'],
            ['avatar' => $uploadedFileDouble],
            ['title' => 'x'],
            ['X-Foo' => 'a'],
            Stream::fromString('body'),
        );

        self::assertSame(['other' => 'xyz'], $serverRequest->withCookieParams(['other' => 'xyz'])->getCookieParams());
        self::assertSame(['session' => 'abc'], $serverRequest->getCookieParams());
        self::assertSame(['b' => '2'], $serverRequest->withQueryParams(['b' => '2'])->getQueryParams());
        self::assertSame(['page' => '2'], $serverRequest->getQueryParams());
        self::assertSame(
            ['logo' => $uploadedFileDouble],
            $serverRequest->withUploadedFiles(['logo' => $uploadedFileDouble])->getUploadedFiles(),
        );
        self::assertSame(['avatar' => $uploadedFileDouble], $serverRequest->getUploadedFiles());
        self::assertSame(['swapped' => true], $serverRequest->withParsedBody(['swapped' => true])->getParsedBody());
        self::assertSame(['title' => 'x'], $serverRequest->getParsedBody());
        self::assertSame('v', $serverRequest->withAttribute('k', 'v')->getAttribute('k'));
        self::assertNull($serverRequest->getAttribute('k'));
        self::assertNull($serverRequest->withoutAttribute('k')->getAttribute('k'));
        self::assertSame('GET', $serverRequest->withMethod('GET')->getMethod());
        self::assertSame('POST', $serverRequest->getMethod());
        self::assertSame('/page?a=1', $serverRequest->getRequestTarget());
        self::assertSame('example.com', $serverRequest->getHeaderLine('Host'));
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
