<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\ETagMiddleware;
use Zef\Framework\Http\HeadMiddleware;
use Zef\Framework\Http\HeadRequest;
use Zef\Framework\Http\HeadResponse;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\MiddlewareHandler;
use Zef\Test\Support\Http\RecordingHandler;

/**
 * @internal
 */
#[CoversClass(HeadMiddleware::class)]
#[CoversClass(HeadRequest::class)]
#[CoversClass(HeadResponse::class)]
#[CoversClass(Response::class)]
#[CoversClass(Stream::class)]
#[CoversClass(ServerRequest::class)]
final class HeadMiddlewareTest extends TestCase
{
    private const string REPRESENTATION = 'the-full-get-representation';

    public function testAHeadRequestExecutesTheHandlersGetLogic(): void
    {
        $handler = $this->recordingHandler();

        $response = new HeadMiddleware()->process($this->request('HEAD'), $handler);

        self::assertSame(
            'GET',
            $handler->observed()->getMethod(),
            'the handler answers the GET logic of the HEAD request',
        );
        self::assertSame(200, $response->getStatusCode());
    }

    public function testTheHandlerReceivesTheWholeRequestUnderTheGetMethod(): void
    {
        $uri = new Uri('https://api.example.com/items?page=2');
        $request = $this->request('HEAD', ['Accept' => 'application/json'], $uri);
        $request = $request->withAttribute('tenant', 'acme');

        $handler = $this->recordingHandler();

        new HeadMiddleware()->process($request, $handler);

        $observed = $handler->observed();
        self::assertSame('acme', $observed->getAttribute('tenant'));
        self::assertSame('application/json', $observed->getHeaderLine('Accept'));
        self::assertSame('page=2', $observed->getUri()->getQuery());
        self::assertSame(['page' => '2'], $observed->getQueryParams());
    }

    public function testTheHeadAnswerCarriesNoBody(): void
    {
        $response = $this->processed('HEAD');

        self::assertSame('', $response->getBody()->getContents(), 'the head view empties the body');
        self::assertSame(0, $response->getBody()->getSize());
        self::assertSame(0, $response->getBody()->tell(), 'the empty stream rests at position zero for the emitter');
        self::assertTrue(
            $response->getBody()->isSeekable(),
            'the emitter rewinds the body before its eligibility pump',
        );
    }

    public function testTheHeadAnswerDropsTheStaleContentLength(): void
    {
        $response = $this->processed('HEAD');

        self::assertFalse(
            $response->hasHeader('content-length'),
            'the length describes a payload that will never arrive',
        );
    }

    public function testTheHeadAnswerKeepsTheValidatorsOfTheFullRepresentation(): void
    {
        $response = new HeadMiddleware()->process(
            $this->request('HEAD'),
            new FixedResponseHandler(new Response(
                200,
                [
                    'Content-Type' => 'application/json',
                    'Content-Length' => '28',
                    'ETag' => '"a1b2c3"',
                    'Last-Modified' => 'Mon, 01 Jan 2024 10:00:00 GMT',
                    'X-Custom' => 'travels',
                ],
                self::REPRESENTATION,
            )),
        );

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('"a1b2c3"', $response->getHeaderLine('ETag'));
        self::assertSame('Mon, 01 Jan 2024 10:00:00 GMT', $response->getHeaderLine('Last-Modified'));
        self::assertSame('travels', $response->getHeaderLine('X-Custom'));
    }

    public function testTheHeadAnswerKeepsTheHandlersStatus(): void
    {
        self::assertSame(201, $this->processed('HEAD', 201)->getStatusCode());
        self::assertSame(404, $this->processed('HEAD', 404)->getStatusCode());
    }

    public function testAGetRequestPassesThroughUntouched(): void
    {
        $request = $this->request('GET');
        $answer = new Response(200, ['Content-Length' => '28'], self::REPRESENTATION);
        $recordingHandler = new RecordingHandler($answer);

        $response = new HeadMiddleware()->process($request, $recordingHandler);

        self::assertSame($answer, $response, 'a GET answer is the handler object itself');
        self::assertSame(
            $request,
            $recordingHandler->observed(),
            'a GET request reaches the handler as the same instance',
        );
        self::assertSame(self::REPRESENTATION, $response->getBody()->getContents());
    }

    public function testAPostRequestPassesThroughWithItsBody(): void
    {
        $handler = $this->recordingHandler();

        $response = new HeadMiddleware()->process($this->request('POST'), $handler);

        self::assertSame('POST', $handler->observed()->getMethod());
        self::assertSame(self::REPRESENTATION, $response->getBody()->getContents());
        self::assertSame('28', $response->getHeaderLine('Content-Length'));
    }

    /**
     * @param non-empty-string $method
     */
    #[DataProvider('provideEveryOtherMethodPassesThroughUntouchedCases')]
    public function testEveryOtherMethodPassesThroughUntouched(string $method): void
    {
        $request = $this->request($method);
        $answer = new Response(200, ['X-Answer' => 'same'], self::REPRESENTATION);
        $recordingHandler = new RecordingHandler($answer);

        $response = new HeadMiddleware()->process($request, $recordingHandler);

        self::assertSame($answer, $response);
        self::assertSame($request, $recordingHandler->observed(), "{$method} reaches the handler as the same instance");
        self::assertSame($method, $recordingHandler->observed()->getMethod());
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideEveryOtherMethodPassesThroughUntouchedCases(): iterable
    {
        yield 'GET' => ['GET'];

        yield 'POST' => ['POST'];

        yield 'PUT' => ['PUT'];

        yield 'PATCH' => ['PATCH'];

        yield 'DELETE' => ['DELETE'];

        yield 'OPTIONS' => ['OPTIONS'];
    }

    /**
     * The lowercase `head` token is a different, unregistered method
     * (RFC 9110 section 9.1: method names are case-sensitive) -- the
     * exact-token gate's pass-through pin.
     */
    public function testTheLowercaseHeadTokenIsAnUnregisteredPassThrough(): void
    {
        $request = $this->request('head');
        $answer = new Response(200, ['Content-Length' => '28'], self::REPRESENTATION);
        $recordingHandler = new RecordingHandler($answer);

        $response = new HeadMiddleware()->process($request, $recordingHandler);

        self::assertSame('head', $recordingHandler->observed()->getMethod());
        self::assertSame($request, $recordingHandler->observed(), 'the unregistered token is never reissued');
        self::assertSame($answer, $response, 'the unregistered token never reaches the head view');
        self::assertSame(self::REPRESENTATION, $response->getBody()->getContents());
    }

    public function testTheHeadViewOfANoContentAnswerIsStillBodyless(): void
    {
        $response = $this->processed('HEAD', 204);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getBody()->getContents());
        self::assertFalse($response->hasHeader('Content-Length'));
    }

    public function testTheHeadViewOfANotModifiedAnswerKeepsTheValidator(): void
    {
        $response = new HeadMiddleware()->process(
            $this->request('HEAD', ['If-None-Match' => '"a1b2c3"']),
            new FixedResponseHandler(new Response(
                304,
                ['ETag' => '"a1b2c3"'],
                '',
            )),
        );

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('"a1b2c3"', $response->getHeaderLine('ETag'));
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testTheHeadFoldComposesWithTheEtagMiddleware(): void
    {
        $response = new HeadMiddleware()->process(
            $this->request('HEAD'),
            new MiddlewareHandler(
                new ETagMiddleware(),
                new FixedResponseHandler(new Response(
                    200,
                    ['Content-Length' => (string) mb_strlen(self::REPRESENTATION)],
                    self::REPRESENTATION,
                )),
            ),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            '"' . bin2hex(hash('sha256', self::REPRESENTATION, true)) . '"',
            $response->getHeaderLine('ETag'),
            'the validator describes the full GET representation, not the emptied head view',
        );
        self::assertFalse($response->hasHeader('Content-Length'));
        self::assertSame('', $response->getBody()->getContents());
    }

    public function testAGetRequestTravelsTheSamePipelineUnfolded(): void
    {
        $response = new HeadMiddleware()->process(
            $this->request('GET'),
            new MiddlewareHandler(
                new ETagMiddleware(),
                new FixedResponseHandler(new Response(
                    200,
                    ['Content-Length' => (string) mb_strlen(self::REPRESENTATION)],
                    self::REPRESENTATION,
                )),
            ),
        );

        self::assertSame(self::REPRESENTATION, $response->getBody()->getContents());
        self::assertSame((string) mb_strlen(self::REPRESENTATION), $response->getHeaderLine('Content-Length'));
        self::assertSame(
            '"' . bin2hex(hash('sha256', self::REPRESENTATION, true)) . '"',
            $response->getHeaderLine('ETag'),
        );
    }

    public function testTheHeadAnswerOfAStreamedBodyIsEmptiedAtRest(): void
    {
        $stream = Stream::fromString(self::REPRESENTATION);
        $stream->seek(4);

        $response = new HeadMiddleware()->process(
            $this->request('HEAD'),
            new FixedResponseHandler(new Response(200, ['Content-Length' => '28'], $stream)),
        );

        self::assertSame('', $response->getBody()->getContents());
        self::assertSame(
            0,
            $response->getBody()->tell(),
            'the head view swaps in a fresh empty stream regardless of the cursor',
        );
    }

    private function processed(string $method, int $status = 200): ResponseInterface
    {
        return new HeadMiddleware()->process(
            $this->request($method),
            $this->recordingHandler($status),
        );
    }

    private function recordingHandler(int $status = 200): RecordingHandler
    {
        return new RecordingHandler(new Response(
            $status,
            ['Content-Length' => '28', 'Content-Type' => 'text/plain'],
            self::REPRESENTATION,
        ));
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(
        string $method,
        array $headers = [],
        ?Uri $uri = null,
    ): ServerRequest {
        $map = [];
        foreach ($headers as $name => $value) {
            $map[mb_strtolower($name)] = [$value];
        }

        return new ServerRequest(
            $method,
            $uri ?? new Uri('https://api.example.com/items'),
            [],
            [],
            ['page' => '2'],
            [],
            null,
            $map,
            Stream::fromString(''),
        );
    }
}
