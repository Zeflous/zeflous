<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimpleXMLElement;
use Zef\Framework\Http\BodyParserMiddleware;
use Zef\Framework\Http\BodyParserPolicy;
use Zef\Framework\Http\MalformedBodyException;
use Zef\Framework\Http\RequestBodyParsers;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\TextBody;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\NonSeekableBody;
use Zef\Test\Support\Http\RecordingHandler;
use Zef\Test\Support\Http\RecordingParser;
use Zef\Test\Support\Http\ThrowingHandler;

/**
 * The body-parser boundary, on its own terms: the request decorated
 * with its deserialized body for every owned media type, the stream
 * left unconsumed for the controller that prefers the raw body, and
 * every honest pass-through in the cheapest-refusal-first order the
 * walk spells -- plus the fail-closed 400 a malformed owned body
 * answers before the handler ever runs.
 *
 * @internal
 */
#[CoversClass(BodyParserMiddleware::class)]
#[CoversClass(RequestBodyParsers::class)]
final class BodyParserMiddlewareTest extends TestCase
{
    public function testAJsonBodyIsDecoratedOntoTheRequestWithoutConsumingTheStream(): void
    {
        $request = $this->request('application/json; charset=utf-8', '{"order": 7}');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        $observed = $recordingHandler->observed();
        self::assertSame(['order' => 7], $observed->getParsedBody(), 'the body is parsed');
        $observed->getBody()->rewind();
        self::assertSame(
            '{"order": 7}',
            (string) $observed->getBody(),
            'the stream still carries the whole body after the parse',
        );
    }

    public function testAnXmlBodyIsDecoratedAsTheElementTree(): void
    {
        $request = $this->request('application/xml', '<order id="7"><item>Coffee</item></order>');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        $parsed = $recordingHandler->observed()->getParsedBody();
        self::assertInstanceOf(SimpleXMLElement::class, $parsed);
        self::assertSame('Coffee', (string) $parsed->item);
    }

    public function testATextBodyIsDecoratedAsTheTextValueObject(): void
    {
        $request = $this->request('text/plain', 'brew notes');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        $parsed = $recordingHandler->observed()->getParsedBody();
        self::assertInstanceOf(TextBody::class, $parsed);
        self::assertSame('brew notes', $parsed->text);
    }

    public function testAFormBodyOnTheNonSapiPathIsDecoded(): void
    {
        $request = $this->request('application/x-www-form-urlencoded', 'order=7&item=coffee');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        self::assertSame(
            ['order' => '7', 'item' => 'coffee'],
            $recordingHandler->observed()->getParsedBody(),
        );
    }

    public function testADisarmedFamilyIsTheInertPassThrough(): void
    {
        $request = $this->request('application/json', '{"order": 7}');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware(new BodyParserPolicy(enabled: false))->process($request, $recordingHandler);

        self::assertSame($request, $recordingHandler->observed(), "the request is the handler's own, untouched");
    }

    public function testARequestWithNoContentTypeLinePassesThrough(): void
    {
        $request = $this->request('', '{"order": 7}');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        self::assertSame($request, $recordingHandler->observed());
    }

    public function testARequestAlreadyCarryingAParsedBodyPassesThroughUntouched(): void
    {
        $request = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            stream: Stream::fromString('order=7'),
        )->withParsedBody(['order' => '7', 'source' => 'the SAPI seam']);
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        self::assertSame(
            ['order' => '7', 'source' => 'the SAPI seam'],
            $recordingHandler->observed()->getParsedBody(),
            "another seam's answer is never re-parsed",
        );
    }

    public function testAMediaTypeTheFamilyDoesNotOwnPassesThrough(): void
    {
        $multipart = $this->request('multipart/form-data; boundary=boundary', '--boundary--');
        $octetStream = $this->request('application/octet-stream', "\x00\x01");
        $svg = $this->request('image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $recordingHandler = new RecordingHandler(new Response(200));

        foreach ([$multipart, $octetStream, $svg] as $request) {
            $this->middleware()->process($request, $recordingHandler);
            self::assertNull($recordingHandler->observed()->getParsedBody(), 'no body is parsed');
        }
    }

    public function testAnEmptyBodyHasNothingToParse(): void
    {
        $request = $this->request('application/json', '');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        self::assertNull($recordingHandler->observed()->getParsedBody(), 'an empty body is not a malformed document');
    }

    public function testAJsonNullDocumentAnswersNoParsedBody(): void
    {
        $request = $this->request('application/json', 'null');
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($request, $recordingHandler);

        self::assertNull(
            $recordingHandler->observed()->getParsedBody(),
            'a null document is the honest no-body answer',
        );
    }

    public function testAnUnreadableStreamPassesThroughRatherThanDraining(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'text/plain'],
            stream: new NonSeekableBody('brew notes'),
        );
        $recordingHandler = new RecordingHandler(new Response(200));

        $this->middleware()->process($serverRequest, $recordingHandler);

        $observed = $recordingHandler->observed();
        self::assertNull($observed->getParsedBody(), 'the family refuses to destroy the request it was handed');
        self::assertSame('brew notes', (string) $observed->getBody(), 'the body is still deliverable downstream');
    }

    public function testAMalformedOwnedBodyRefusesBeforeTheHandlerRuns(): void
    {
        $request = $this->request('application/json', '{"order": ');

        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('The request body is not a well-formed JSON document');

        $this->middleware()->process(
            $request,
            new ThrowingHandler(new RuntimeException('the handler must never run')),
        );
    }

    public function testTheInjectableStrategyTableIsTheSeamTheHostOwns(): void
    {
        $recordingParser = new RecordingParser();
        $request = $this->request('application/json', '{"order": 7}');
        $recordingHandler = new RecordingHandler(new Response(200));

        new BodyParserMiddleware(
            new BodyParserPolicy(),
            new RequestBodyParsers([$recordingParser]),
        )->process($request, $recordingHandler);

        self::assertSame('{"order": 7}', $recordingParser->parsed(), "the host's table answered the parse");
        self::assertSame(['order' => 7], $recordingHandler->observed()->getParsedBody());
    }

    public function testTheAlreadyParsedGateNeverConsultsTheTable(): void
    {
        $recordingParser = new RecordingParser();
        $request = $this->request('application/json', '{"order": 7}')
            ->withParsedBody(['order' => 7, 'source' => 'the host'])
        ;
        $recordingHandler = new RecordingHandler(new Response(200));

        new BodyParserMiddleware(
            new BodyParserPolicy(),
            new RequestBodyParsers([$recordingParser]),
        )->process($request, $recordingHandler);

        self::assertNull($recordingParser->parsed(), 'the table is never asked about an already-parsed request');
    }

    private function middleware(?BodyParserPolicy $bodyParserPolicy = null): BodyParserMiddleware
    {
        return new BodyParserMiddleware($bodyParserPolicy ?? new BodyParserPolicy());
    }

    private function request(string $contentType, string $body): ServerRequest
    {
        return new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: $contentType === '' ? [] : ['Content-Type' => $contentType],
            stream: Stream::fromString($body),
        );
    }
}
