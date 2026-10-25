<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpWireBlock;
use Zef\Framework\Http\HttpWireRequest;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;

/**
 * The wire-dump family's request half: the request block over its own
 * request line -- method, resolved origin-form target (path and
 * query), protocol version -- with the head closed by the bare CRLF
 * pair and the body following verbatim.
 *
 * @internal
 */
#[CoversClass(HttpWireRequest::class)]
#[CoversClass(HttpWireBlock::class)]
final class HttpWireRequestTest extends TestCase
{
    public function testARequestWithHeadersAndABodyAnswersItsFullWireBlock(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('http://api.example.com/mirror/name?flag=1'),
            [],
            [],
            [],
            [],
            null,
            [
                'Host' => 'api.example.com',
                'Accept' => ['application/json', 'text/html'],
            ],
            Stream::fromString('the payload'),
        );

        self::assertSame(
            "POST /mirror/name?flag=1 HTTP/1.1\r\n"
            . "Host: api.example.com\r\n"
            . "Accept: application/json\r\n"
            . "Accept: text/html\r\n"
            . "\r\n"
            . 'the payload',
            HttpWireRequest::rendered($serverRequest, 64),
        );
    }

    public function testABodylessRequestEndsAtTheClosingPair(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://localhost/ping'));

        self::assertSame(
            "GET /ping HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "\r\n",
            HttpWireRequest::rendered($serverRequest, 64),
        );
    }

    public function testARequestWithoutExplicitHeadersStillCarriesItsHostLine(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('http://localhost/x'), [], [], [], [], null, []);

        self::assertSame(
            "GET /x HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "\r\n",
            HttpWireRequest::rendered($serverRequest, 64),
        );
    }

    public function testABodyBeyondTheBoundIsTruncatedInsideTheRequestBlock(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('http://localhost/echo'),
            [],
            [],
            [],
            [],
            null,
            [],
            Stream::fromString('0123456789A'),
        );

        self::assertSame(
            "POST /echo HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "\r\n"
            . "01234567\n[3 of 11 bytes truncated]\n",
            HttpWireRequest::rendered($serverRequest, 8),
        );
    }
}
