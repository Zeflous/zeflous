<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\BodyParserPolicy;
use Zef\Framework\Http\FormBodyParser;
use Zef\Framework\Http\JsonBodyParser;
use Zef\Framework\Http\MalformedBodyException;
use Zef\Framework\Http\RequestBodyParsers;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\TextBodyParser;
use Zef\Framework\Http\Uri;
use Zef\Framework\Http\XmlBodyParser;
use Zef\Test\Support\Http\ClaimingParser;

/**
 * The negotiation table, on its own terms: each owned media type
 * routes to its strategy, the media types the family does not own
 * (multipart included) answer no strategy, and the first declared
 * owner wins in a host-built table with overlapping grammars -- plus
 * the request-level walk the table owns: the parser a request
 * carries (or none, when the type line is absent or another seam
 * already answered), and the parsed body the family answers for the
 * request's own non-empty, capturable body.
 *
 * @internal
 */
#[CoversClass(RequestBodyParsers::class)]
final class RequestBodyParsersTest extends TestCase
{
    public function testTheStandardFamilyRoutesEveryOwnedMediaTypeToItsStrategy(): void
    {
        $parsers = RequestBodyParsers::standard(new BodyParserPolicy());

        $routes = [
            'application/json' => JsonBodyParser::class,
            'APPLICATION/VND.API+JSON' => JsonBodyParser::class,
            'application/xml' => XmlBodyParser::class,
            'text/xml' => XmlBodyParser::class,
            'application/atom+xml' => XmlBodyParser::class,
            'application/x-www-form-urlencoded' => FormBodyParser::class,
            'text/plain' => TextBodyParser::class,
        ];

        foreach ($routes as $mediaType => $strategy) {
            $routed = $parsers->forMediaType($mediaType);
            self::assertInstanceOf($strategy, $routed, $mediaType . ' routes to its strategy');
        }
    }

    public function testTheStandardFamilyOwnsNoStrategyForTheUnownedMediaTypes(): void
    {
        $parsers = RequestBodyParsers::standard(new BodyParserPolicy());

        $unowned = [
            '',
            'multipart/form-data',
            'application/octet-stream',
            'image/svg+xml',
            'text/html',
            'application/x-yaml',
        ];

        foreach ($unowned as $mediaType) {
            self::assertNull($parsers->forMediaType($mediaType), $mediaType . ' is not owned');
        }
    }

    public function testTheStandardFamilyCarriesThePolicysOwnDepthCeiling(): void
    {
        $parsers = RequestBodyParsers::standard(new BodyParserPolicy(jsonDepth: 2));

        $routed = $parsers->forMediaType('application/json');
        self::assertInstanceOf(JsonBodyParser::class, $routed);

        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('Maximum stack depth exceeded');

        $routed->parse('{"a": {"b": {"c": 1}}}');
    }

    public function testARequestWithNoContentTypeLineOwnsNoParser(): void
    {
        $serverRequest = new ServerRequest('POST', new Uri('https://app.example.com/orders'));

        self::assertNull(RequestBodyParsers::standard(new BodyParserPolicy())->forRequest($serverRequest));
    }

    public function testARequestAlreadyCarryingAParsedBodyOwnsNoParser(): void
    {
        $request = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString('{"order": 7}'),
        )->withParsedBody(['order' => 7]);

        self::assertNull(RequestBodyParsers::standard(new BodyParserPolicy())->forRequest($request));
    }

    public function testAContentTypeLineWithParametersIsTheTypeTheGrammarWalks(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            stream: Stream::fromString('{"order": 7}'),
        );

        $parsers = RequestBodyParsers::standard(new BodyParserPolicy());

        self::assertInstanceOf(JsonBodyParser::class, $parsers->forRequest($serverRequest));
        self::assertSame(['order' => 7], $parsers->parsedBody($serverRequest));
    }

    public function testTheWalkReadsTheBodyWithoutConsumingTheStream(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString('{"order": 7}'),
        );

        RequestBodyParsers::standard(new BodyParserPolicy())->parsedBody($serverRequest);

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

        self::assertNull(RequestBodyParsers::standard(new BodyParserPolicy())->parsedBody($serverRequest));
    }

    public function testAJsonNullDocumentAnswersNoParsedBody(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString('null'),
        );

        self::assertNull(RequestBodyParsers::standard(new BodyParserPolicy())->parsedBody($serverRequest));
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

        RequestBodyParsers::standard(new BodyParserPolicy())->parsedBody($serverRequest);
    }

    public function testAHostBuiltTableAnswersItsFirstDeclaredOwner(): void
    {
        $claimingParser = new ClaimingParser(new JsonBodyParser(), new FormBodyParser());
        $requestBodyParsers = new RequestBodyParsers([
            $claimingParser,
            new FormBodyParser(),
        ]);

        $routed = $requestBodyParsers->forMediaType('application/x-www-form-urlencoded');

        self::assertSame($claimingParser, $routed, 'the first declared owner wins');
    }
}
