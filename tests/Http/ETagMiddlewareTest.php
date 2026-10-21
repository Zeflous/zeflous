<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Http\ETagMiddleware;
use Zef\Framework\Http\HttpDate;
use Zef\Framework\Http\IfModifiedSince;
use Zef\Framework\Http\IfNoneMatch;
use Zef\Framework\Http\NotModifiedResponse;
use Zef\Framework\Http\RepresentationBody;
use Zef\Framework\Http\RequestPreconditions;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StrongEtag;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\LowercaseMethodServerRequest;
use Zef\Test\Support\Http\NonSeekableBody;

/**
 * @internal
 */
#[CoversClass(ETagMiddleware::class)]
#[CoversClass(NotModifiedResponse::class)]
#[CoversClass(RequestPreconditions::class)]
#[CoversClass(RepresentationBody::class)]
#[CoversClass(StrongEtag::class)]
#[CoversClass(IfNoneMatch::class)]
#[CoversClass(IfModifiedSince::class)]
#[CoversClass(HttpDate::class)]
#[CoversClass(Response::class)]
#[CoversClass(Stream::class)]
#[CoversClass(ServerRequest::class)]
final class ETagMiddlewareTest extends TestCase
{
    private const string LAST_MODIFIED = 'Mon, 01 Jan 2024 10:00:00 GMT';

    public function testAFreshGetResponseCarriesTheStrongValidator(): void
    {
        $response = $this->processed('conditional-body', 'GET', 200);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->etagOf('conditional-body'), $response->getHeaderLine('ETag'));
        self::assertSame(0, $response->getBody()->tell(), 'the cursor is restored after the hashing read');
    }

    /**
     * The strong validator is the SHA-256 digest of the representation,
     * hex-encoded and quoted -- the exact wire form RFC 9110 §8.8.3
     * describes for an opaque validator.
     */
    public function testTheValidatorIsTheSha256DigestOfTheRepresentation(): void
    {
        $response = $this->processed('payload', 'GET', 200);

        self::assertSame('"' . bin2hex(hash('sha256', 'payload', true)) . '"', $response->getHeaderLine('ETag'));
    }

    public function testAMatchingIfNoneMatchShortCircuitsToABodyLess304(): void
    {
        $etag = $this->etagOf('hello');
        $response = $this->processed('hello', 'GET', 200, ['If-None-Match' => $etag, 'Content-Length' => '5']);

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody(), 'a 304 carries no content (RFC 9110 §15.4.5)');
        self::assertSame($etag, $response->getHeaderLine('ETag'), 'the validator travels with the 304');
        self::assertFalse($response->hasHeader('Content-Length'), 'the stale framing header is dropped');
    }

    public function testAHeadRequestIsAlsoConditional(): void
    {
        $response = $this->processed('b', 'HEAD', 200);

        self::assertSame($this->etagOf('b'), $response->getHeaderLine('ETag'));
    }

    public function testALowercaseMethodTokenIsStillConditional(): void
    {
        $lowercaseMethodServerRequest = new LowercaseMethodServerRequest($this->request());
        $inner = new Response(200, [], 'b');
        $response = new ETagMiddleware()->process($lowercaseMethodServerRequest, $this->handler($inner));

        self::assertSame(
            $this->etagOf('b'),
            $response->getHeaderLine('ETag'),
            'a lowercase "get" is still a conditional-GET method',
        );
    }

    public function testPostRequestsAreNotConditional(): void
    {
        $inner = new Response(200, [], 'created');
        $response = new ETagMiddleware()->process($this->request('POST'), $this->handler($inner));

        self::assertSame($inner, $response, 'the response object flows through untouched');
        self::assertFalse($response->hasHeader('ETag'));
    }

    public function testNonTwoHundredResponsesPassThroughUntouched(): void
    {
        $inner = new Response(201, [], 'created');
        $response = new ETagMiddleware()->process($this->request(), $this->handler($inner));

        self::assertSame($inner, $response);
        self::assertFalse($response->hasHeader('ETag'), 'non-200 responses must not gain a validator');
    }

    /**
     * Audit #303 regression (reference lineage): consuming the body to
     * hash it leaves a seekable stream's cursor at EOF; the shared stream
     * flows on to the emitter, which must still ship the full
     * representation on the cache-miss path.
     */
    public function testTheStreamCursorIsRestoredAfterHashing(): void
    {
        $response = $this->processed('hello', 'GET', 200);

        $body = $response->getBody();
        self::assertSame(0, $body->tell(), 'the cursor must be back at the start after the (string) read');
        self::assertSame('hello', (string) $body, 'the full representation is still readable downstream');
    }

    public function testANonSeekableBodyIsConsumedWithoutRewinding(): void
    {
        $response = new ETagMiddleware()->process(
            $this->request(),
            $this->handler(new Response(200, [], new NonSeekableBody('payload'))),
        );

        self::assertSame($this->etagOf('payload'), $response->getHeaderLine('ETag'));
    }

    public function testAnEmptyBodiedResponseCarriesNoValidator(): void
    {
        $inner = new Response(200);
        $response = new ETagMiddleware()->process($this->request(), $this->handler($inner));

        self::assertSame($inner, $response);
        self::assertFalse($response->hasHeader('ETag'));
    }

    public function testAHandlerSuppliedValidatorIsNeverOverwritten(): void
    {
        $response = $this->processed('body', 'GET', 200, [], ['ETag' => '"handler-etag"']);

        self::assertSame('"handler-etag"', $response->getHeaderLine('ETag'));
    }

    /**
     * RFC 9110 §13.1.2: the condition is judged against the entity-tag
     * of the selected representation -- the validator the response
     * carries, not the hash the middleware would compute.
     */
    public function testADeclaredValidatorAnswersItsOwnRevalidation(): void
    {
        $response = $this->processed(
            'payload',
            'GET',
            200,
            ['If-None-Match' => '"handler-etag"'],
            ['ETag' => '"handler-etag"'],
        );

        self::assertSame(304, $response->getStatusCode(), 'the declared validator is the one the condition is judged against');
        self::assertSame('"handler-etag"', $response->getHeaderLine('ETag'));
    }

    public function testADeclaredValidatorIsJudgedNotTheComputedOne(): void
    {
        $response = $this->processed(
            'payload',
            'GET',
            200,
            ['If-None-Match' => $this->etagOf('payload')],
            ['ETag' => '"handler-etag"'],
        );

        self::assertSame(200, $response->getStatusCode(), 'a body-hash candidate does not revalidate a handler-managed validator');
        self::assertSame('"handler-etag"', $response->getHeaderLine('ETag'));
    }

    public function testTheStarFormMatchesWhenADeclaredValidatorExists(): void
    {
        $response = $this->processed(
            '',
            'GET',
            200,
            ['If-None-Match' => '*'],
            ['ETag' => '"handler-etag"'],
        );

        self::assertSame(304, $response->getStatusCode(), 'a current representation exists when the handler declared its validator');
    }

    #[DataProvider('provideIfNoneMatchGrammarVariantsCases')]
    public function testIfNoneMatchGrammarVariants(string $ifNoneMatch, int $expectedStatus): void
    {
        $response = $this->processed('payload', 'GET', 200, ['If-None-Match' => $ifNoneMatch]);

        self::assertSame($expectedStatus, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideIfNoneMatchGrammarVariantsCases(): iterable
    {
        $etag = '"' . bin2hex(hash('sha256', 'payload', true)) . '"';

        yield 'star with padding' => [' * ', 304];

        yield 'weak form' => ['W/' . $etag, 304];

        yield 'lowercase weak form' => ['w/' . $etag, 304];

        yield 'candidate list' => ['"zzz", ' . $etag, 304];

        yield 'list with empty members' => [',  , ' . $etag . ' ,', 304];

        yield 'non-matching list' => ['"zzz"', 200];
    }

    /**
     * Regression P-13 (issue #171 in the reference lineage): RFC 9110
     * §13.1.3 -- If-Modified-Since MUST be ignored whenever the request
     * carries If-None-Match, even a non-matching one.
     */
    public function testIfNoneMatchShadowsIfModifiedSincePerRfc9110(): void
    {
        $shadowed = $this->processed(
            '',
            'GET',
            200,
            ['If-None-Match' => '"stale-etag"', 'If-Modified-Since' => self::LAST_MODIFIED],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(200, $shadowed->getStatusCode(), 'IMS must not 304 while the request carries If-None-Match.');
        self::assertFalse($shadowed->hasHeader('ETag'));

        $matching = $this->processed(
            'body',
            'GET',
            200,
            ['If-None-Match' => $this->etagOf('body'), 'If-Modified-Since' => 'Mon, 01 Jan 2024 09:59:59 GMT'],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $matching->getStatusCode(), 'a matching If-None-Match wins over a disagreeing IMS.');
    }

    /**
     * Regression P-13, second half: a body-carrying response with
     * Last-Modified must 304 an IMS-only request; equal timestamps count
     * as not modified (whole-second comparison).
     */
    public function testIfModifiedSinceAppliesToAnyResponseCarryingLastModified(): void
    {
        $withBody = $this->processed(
            'b',
            'GET',
            200,
            ['If-Modified-Since' => self::LAST_MODIFIED],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $withBody->getStatusCode(), 'IMS equal to Last-Modified must 304 even with a body');
        self::assertSame(
            $this->etagOf('b'),
            $withBody->getHeaderLine('ETag'),
            'the computed validator travels with the 304',
        );
        self::assertSame('', (string) $withBody->getBody());

        $stale = $this->processed(
            'b',
            'GET',
            200,
            ['If-Modified-Since' => 'Mon, 01 Jan 2024 09:59:59 GMT'],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(200, $stale->getStatusCode(), "the representation changed since the client's copy");
        self::assertSame($this->etagOf('b'), $stale->getHeaderLine('ETag'));

        $equalWithoutBody = $this->processed(
            '',
            'GET',
            200,
            ['If-Modified-Since' => self::LAST_MODIFIED],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $equalWithoutBody->getStatusCode());
        self::assertFalse($equalWithoutBody->hasHeader('ETag'), 'no body means no computed validator');

        $newer = $this->processed(
            '',
            'GET',
            200,
            ['If-Modified-Since' => 'Mon, 01 Jan 2024 10:00:01 GMT'],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $newer->getStatusCode(), 'a client newer than the server is not modified');
    }

    #[DataProvider('provideAMalformedIfModifiedSinceIsIgnoredNotFatalCases')]
    public function testAMalformedIfModifiedSinceIsIgnoredNotFatal(string $ifModifiedSince): void
    {
        $response = $this->processed(
            '',
            'GET',
            200,
            ['If-Modified-Since' => $ifModifiedSince],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(200, $response->getStatusCode(), "an invalid IMS '{$ifModifiedSince}' is no precondition");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedIfModifiedSinceIsIgnoredNotFatalCases(): iterable
    {
        yield 'not a date' => ['not-a-date'];

        yield 'trailing junk' => ['Mon, 01 Jan 2024 10:00:00 GMT junk'];

        yield 'rolled-over day' => ['Mon, 32 Jan 2024 10:00:00 GMT'];

        yield 'rolled-over second' => ['Mon, 01 Jan 2024 10:00:60 GMT'];
    }

    public function testAZonedOffsetTimestampComparesThroughItsTimestamp(): void
    {
        $response = $this->processed(
            '',
            'GET',
            200,
            ['If-Modified-Since' => 'Mon, 01 Jan 2024 10:00:00 +05:00'],
            ['Last-Modified' => 'Mon, 01 Jan 2024 05:00:00 GMT'],
        );

        self::assertSame(304, $response->getStatusCode(), 'the offset form is the same instant as the GMT form');
    }

    public function testAZoneLessTimestampIsReadInTheGmtFrame(): void
    {
        $response = $this->processed(
            '',
            'GET',
            200,
            ['If-Modified-Since' => 'Mon, 01 Jan 2024 10:00:00'],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $response->getStatusCode(), 'a date without its zone designator is read in GMT');
    }

    public function testFullDayNamesParseThroughTheAbbreviationSpecifier(): void
    {
        $response = $this->processed(
            '',
            'GET',
            200,
            ['If-Modified-Since' => 'Monday, 01 Jan 2024 10:00:00 GMT'],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $response->getStatusCode(), '"Monday" parses through the D specifier on this engine');
    }

    public function testAnIfNoneMatchStarStillNeedsAValidatorToMatch(): void
    {
        $inner = new Response(200);
        $response = new ETagMiddleware()->process(
            $this->request('GET', ['If-None-Match' => '*']),
            $this->handler($inner),
        );

        self::assertSame($inner, $response, 'with no body and no Last-Modified there is nothing to validate against');
    }

    public function testAWhitespaceOnlyIfNoneMatchIsTreatedAsAbsent(): void
    {
        $response = $this->processed(
            '',
            'GET',
            200,
            ['If-None-Match' => ' ', 'If-Modified-Since' => self::LAST_MODIFIED],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $response->getStatusCode(), 'an OWS-only If-None-Match is an absent precondition');
    }

    public function testAnIfModifiedSinceWithSurroundingWhitespaceStillParses(): void
    {
        $response = $this->processed(
            '',
            'GET',
            200,
            ['If-Modified-Since' => ' ' . self::LAST_MODIFIED . ' '],
            ['Last-Modified' => self::LAST_MODIFIED],
        );

        self::assertSame(304, $response->getStatusCode());
    }

    /**
     * @param array<string, string> $requestHeaders
     * @param array<string, string> $responseHeaders
     */
    private function processed(
        string $body,
        string $method,
        int $status,
        array $requestHeaders = [],
        array $responseHeaders = [],
    ): ResponseInterface {
        return new ETagMiddleware()->process(
            $this->request($method, $requestHeaders),
            $this->handler(new Response($status, $responseHeaders, $body)),
        );
    }

    private function etagOf(string $body): string
    {
        return '"' . bin2hex(hash('sha256', $body, true)) . '"';
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method = 'GET', array $headers = []): ServerRequest
    {
        $map = [];
        foreach ($headers as $name => $value) {
            $map[mb_strtolower($name)] = [$value];
        }

        return new ServerRequest(
            $method,
            new Uri('https://api.example.com/etag'),
            [],
            [],
            [],
            [],
            null,
            $map,
            Stream::fromString(''),
        );
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new FixedResponseHandler($response);
    }
}
