<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\BodyParserPolicy;
use Zef\Framework\Http\JsonBodyParser;
use Zef\Framework\Http\MalformedBodyException;
use Zef\Framework\Http\ParsedBodyReader;
use Zef\Framework\Http\RequestBodyParsers;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;

/**
 * The request reader, on its own terms: the parser a request carries
 * (or none, when the type line is absent or another seam already
 * answered), and the parsed body the family answers for the request's
 * own non-empty, capturable body -- read without consuming the stream.
 *
 * @internal
 */
#[CoversClass(ParsedBodyReader::class)]
#[CoversClass(RequestBodyParsers::class)]
final class ParsedBodyReaderTest extends TestCase
{
    public function testARequestWithNoContentTypeLineOwnsNoParser(): void
    {
        $serverRequest = new ServerRequest('POST', new Uri('https://app.example.com/orders'));

        self::assertNull($this->reader()->forRequest($serverRequest));
    }

    public function testARequestAlreadyCarryingAParsedBodyOwnsNoParser(): void
    {
        $request = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString('{"order": 7}'),
        )->withParsedBody(['order' => 7]);

        self::assertNull($this->reader()->forRequest($request));
    }

    public function testAContentTypeLineWithParametersIsTheTypeTheGrammarWalks(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            stream: Stream::fromString('{"order": 7}'),
        );

        self::assertInstanceOf(
            JsonBodyParser::class,
            $this->reader()->forRequest($serverRequest),
        );
    }

    public function testTheWalkDecoratesAJsonRequestWithoutConsumingTheStream(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString('{"order": 7}'),
        );

        self::assertSame(['order' => 7], $this->reader()->parsedBody($serverRequest));
        $serverRequest->getBody()->rewind();
        self::assertSame('{"order": 7}', (string) $serverRequest->getBody());
    }

    public function testAnEmptyBodyAnswersNoParsedBody(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString(''),
        );

        self::assertNull($this->reader()->parsedBody($serverRequest));
    }

    public function testAJsonNullDocumentAnswersNoParsedBody(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString('null'),
        );

        self::assertNull($this->reader()->parsedBody($serverRequest));
    }

    public function testAMalformedOwnedBodyRefusesThroughTheWalk(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString('{"order": '),
        );

        $this->expectException(MalformedBodyException::class);

        $this->reader()->parsedBody($serverRequest);
    }

    private function reader(): ParsedBodyReader
    {
        return new ParsedBodyReader(RequestBodyParsers::standard(new BodyParserPolicy()));
    }
}
