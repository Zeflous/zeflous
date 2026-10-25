<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpWireBody;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Tooling\HttpDumpText;
use Zef\Test\Support\Tooling\HttpExchange;

/**
 * The raw dump command's text form: the answered exchange over its
 * two section markers, each block rendered by the HTTP layer's own
 * wire folds.
 *
 * @internal
 */
#[CoversClass(HttpDumpText::class)]
final class HttpDumpTextTest extends TestCase
{
    public function testTheDumpSpellsBothSectionsOfTheAnsweredExchange(): void
    {
        $httpExchange = new HttpExchange(
            new ServerRequest(
                'POST',
                new Uri('http://localhost/echo'),
                [],
                [],
                [],
                [],
                null,
                ['Accept' => 'text/plain'],
                Stream::fromString('payload'),
            ),
            new Response(201, ['Content-Type' => 'text/plain'], 'mirrored'),
        );

        self::assertSame(
            "-- request --\r\n"
            . "POST /echo HTTP/1.1\r\n"
            . "Accept: text/plain\r\n"
            . "Host: localhost\r\n"
            . "\r\n"
            . 'payload'
            . "\r\n\r\n-- response --\r\n"
            . "HTTP/1.1 201 Created\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . 'mirrored',
            HttpDumpText::rendered($httpExchange),
        );
    }

    public function testTheDefaultBodyBoundTruncatesAnOversizedBody(): void
    {
        $body = str_repeat('x', HttpWireBody::DEFAULT_BOUND + 1);

        $httpExchange = new HttpExchange(
            new ServerRequest('GET', new Uri('http://localhost/ping')),
            new Response(200, [], $body),
        );

        $dump = HttpDumpText::rendered($httpExchange);

        self::assertStringEndsWith(
            '[1 of ' . (HttpWireBody::DEFAULT_BOUND + 1) . " bytes truncated]\n",
            $dump,
        );
    }
}
