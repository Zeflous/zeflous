<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CompressedResponse;
use Zef\Framework\Http\CompressionEligibility;
use Zef\Framework\Http\CompressionMiddleware;
use Zef\Framework\Http\CompressionNegotiator;
use Zef\Framework\Http\CompressionPolicy;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\LyingSizeStream;

/**
 * The compression boundary, on its own terms: the compressed answer
 * with its coding declared, its `Vary` line and its stale length
 * dropped; every honest pass-through in the cheapest-refusal-first
 * order the walk spells; and the inert posture of a disarmed family.
 *
 * @internal
 */
#[CoversClass(CompressionMiddleware::class)]
#[CoversClass(CompressionPolicy::class)]
#[CoversClass(CompressionNegotiator::class)]
#[CoversClass(CompressionEligibility::class)]
#[CoversClass(CompressedResponse::class)]
final class CompressionMiddlewareTest extends TestCase
{
    private const string PAYLOAD = "The output-compression engine answers the roadmap's Phase 3.2. ";

    private const string GZIPPED = 'gzip';

    public function testACompressibleAnswerIsCompressedWithItsFramingHonest(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $response = $this->middleware()->process(
            $this->request('gzip'),
            new FixedResponseHandler(
                new Response(200, ['Content-Type' => 'text/plain', 'Content-Length' => '6300'], $payload),
            ),
        );

        self::assertSame(self::GZIPPED, $response->getHeaderLine('Content-Encoding'));
        self::assertSame('Accept-Encoding', $response->getHeaderLine('Vary'));
        self::assertSame(
            '',
            $response->getHeaderLine('Content-Length'),
            'the uncompressed length is stale and dropped',
        );
        self::assertSame($payload, gzdecode((string) $response->getBody()));
        self::assertLessThan(mb_strlen($payload), $response->getBody()->getSize());
    }

    public function testTheHandlersOwnVaryTokensSurviveAheadOfTheEncoding(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $response = $this->middleware()->process(
            $this->request('gzip'),
            new FixedResponseHandler(new Response(200, ['Vary' => 'Accept'], $payload)),
        );

        self::assertSame('Accept, Accept-Encoding', $response->getHeaderLine('Vary'));
    }

    public function testADisarmedFamilyIsInert(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $response = $this->middleware(enabled: false)->process(
            $this->request('gzip'),
            new FixedResponseHandler(new Response(200, [], $payload)),
        );

        self::assertSame('', $response->getHeaderLine('Content-Encoding'));
        self::assertSame('', $response->getHeaderLine('Vary'));
        self::assertSame(mb_strlen($payload), $response->getBody()->getSize(), "the body is the handler's own");
    }

    public function testAClientWithoutTheHeaderAnswersIdentity(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $response = $this->middleware()->process(
            $this->request(''),
            new FixedResponseHandler(new Response(200, [], $payload)),
        );

        self::assertSame('', $response->getHeaderLine('Content-Encoding'));
        self::assertSame('', $response->getHeaderLine('Vary'));
        self::assertSame($payload, (string) $response->getBody());
    }

    public function testABodyBelowTheThresholdIsNotWorthTheEngine(): void
    {
        $response = $this->middleware()->process(
            $this->request('gzip'),
            new FixedResponseHandler(new Response(200, [], 'tiny')),
        );

        self::assertSame('', $response->getHeaderLine('Content-Encoding'));
        self::assertSame(4, $response->getBody()->getSize());
    }

    public function testABodyAtTheThresholdCompresses(): void
    {
        $response = $this->middleware()->process(
            $this->request('gzip'),
            new FixedResponseHandler(new Response(200, [], str_repeat('a', 1024))),
        );

        self::assertSame(self::GZIPPED, $response->getHeaderLine('Content-Encoding'));
    }

    public function testAThresholdOfOneStillRefusesTheEmptyBody(): void
    {
        $response = $this->middleware(minimumBytes: 1)->process(
            $this->request('gzip'),
            new FixedResponseHandler(new Response(200, [], '')),
        );

        self::assertSame('', $response->getHeaderLine('Content-Encoding'));
        self::assertSame(0, $response->getBody()->getSize());
    }

    #[DataProvider('provideABodylessStatusAnswersItselfCases')]
    public function testABodylessStatusAnswersItself(int $status): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $response = $this->middleware()->process(
            $this->request('gzip'),
            new FixedResponseHandler(new Response($status, [], $payload)),
        );

        self::assertSame('', $response->getHeaderLine('Content-Encoding'));
        self::assertSame(mb_strlen($payload), $response->getBody()->getSize());
    }

    /** @return iterable<string, array{int}> */
    public static function provideABodylessStatusAnswersItselfCases(): iterable
    {
        yield 'informational' => [103];

        yield 'no content' => [204];

        yield 'reset content' => [205];

        yield 'not modified' => [304];
    }

    public function testAnAlreadyEncodedAnswerIsNeverCompressedTwice(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $response = $this->middleware()->process(
            $this->request('gzip'),
            new FixedResponseHandler(
                new Response(200, ['Content-Encoding' => 'gzip'], $payload),
            ),
        );

        self::assertSame(self::GZIPPED, $response->getHeaderLine('Content-Encoding'));
        self::assertSame($payload, (string) $response->getBody(), "the handler's own encoding is left alone");
    }

    public function testASecondCompositionOfTheFamilyIsThePassThrough(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $compressed = $this->middleware()->process(
            $this->request('gzip'),
            new FixedResponseHandler(new Response(200, [], $payload)),
        );

        $twice = $this->middleware()->process($this->request('gzip'), new FixedResponseHandler($compressed));

        self::assertSame(
            (string) $compressed->getBody(),
            (string) $twice->getBody(),
            'the composed pipeline compresses exactly once',
        );
    }

    public function testABodyWhoseSizeTheStreamCannotReportCompressesWithoutTheThreshold(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $unknownSize = new Response(
            200,
            [],
            new LyingSizeStream(Stream::fromString($payload), null),
        );

        $response = $this->middleware()->process($this->request('gzip'), new FixedResponseHandler($unknownSize));

        self::assertSame(self::GZIPPED, $response->getHeaderLine('Content-Encoding'));
        self::assertSame($payload, gzdecode((string) $response->getBody()));
    }

    public function testTheNegotiatedCodingIsTheOneTheAnswerDeclares(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $response = $this->middleware()->process(
            $this->request('gzip;q=0, deflate'),
            new FixedResponseHandler(new Response(200, [], $payload)),
        );

        self::assertSame('deflate', $response->getHeaderLine('Content-Encoding'));
        self::assertSame($payload, gzuncompress((string) $response->getBody()));
    }

    public function testARuntimeWithoutTheEngineAnswersIdentity(): void
    {
        $payload = str_repeat(self::PAYLOAD, 100);
        $compressionMiddleware = new CompressionMiddleware(
            new CompressionPolicy(true, 1024, 6),
            new CompressionNegotiator([]),
        );

        $response = $compressionMiddleware->process(
            $this->request('gzip, deflate, br, *'),
            new FixedResponseHandler(new Response(200, [], $payload)),
        );

        self::assertSame('', $response->getHeaderLine('Content-Encoding'));
        self::assertSame($payload, (string) $response->getBody());
    }

    private function request(string $acceptEncoding): ServerRequest
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example.com/health'));

        if ($acceptEncoding === '') {
            return $serverRequest;
        }

        $message = $serverRequest->withHeader('Accept-Encoding', $acceptEncoding);
        self::assertInstanceOf(ServerRequest::class, $message, 'the with* chain must preserve the request type.');

        return $message;
    }

    private function middleware(bool $enabled = true, int $minimumBytes = 1024): CompressionMiddleware
    {
        return new CompressionMiddleware(
            new CompressionPolicy($enabled, $minimumBytes, 6),
            new CompressionNegotiator(['gzip', 'deflate']),
        );
    }
}
