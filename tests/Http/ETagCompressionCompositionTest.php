<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Zef\Framework\Http\CompressedBody;
use Zef\Framework\Http\CompressedResponse;
use Zef\Framework\Http\CompressionEligibility;
use Zef\Framework\Http\CompressionMiddleware;
use Zef\Framework\Http\CompressionNegotiator;
use Zef\Framework\Http\CompressionPolicy;
use Zef\Framework\Http\ETagMiddleware;
use Zef\Framework\Http\RepresentationBody;
use Zef\Framework\Http\RepresentationSnapshot;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\MiddlewareHandler;
use Zef\Test\Support\Http\NonSeekableBody;

/**
 * The conditional-GET and compression families composed (issue #771's
 * reproduction battery): a body-reading middleware ahead of the
 * compression engine, over a body the stream cannot rewind. The
 * conditional read consumes the representation to hash it; the wire
 * answer must then carry the whole representation -- not the gzip of
 * the nothing a consumed non-seekable stream leaves behind, a
 * corruption every header called success while it happened.
 *
 * @internal
 */
#[CoversClass(ETagMiddleware::class)]
#[CoversClass(RepresentationBody::class)]
#[CoversClass(RepresentationSnapshot::class)]
#[CoversClass(CompressionMiddleware::class)]
#[CoversClass(CompressionEligibility::class)]
#[CoversClass(CompressedResponse::class)]
#[CoversClass(CompressedBody::class)]
#[CoversClass(CompressionPolicy::class)]
#[CoversClass(CompressionNegotiator::class)]
#[CoversClass(Response::class)]
#[CoversClass(Stream::class)]
#[CoversClass(ServerRequest::class)]
final class ETagCompressionCompositionTest extends TestCase
{
    /** Exactly 64 bytes, so 64 repeats answer the issue's 4096-byte body. */
    private const string PAYLOAD = 'The consumed body rides onward as its seekable snapshot, whole. ';

    public function testAConsumedNonSeekableRepresentationCompressesWhole(): void
    {
        $payload = str_repeat(self::PAYLOAD, 64);
        $response = $this->conditionalPipeline(new NonSeekableBody($payload));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('gzip', $response->getHeaderLine('Content-Encoding'));
        self::assertSame($this->etagOf($payload), $response->getHeaderLine('ETag'));
        self::assertSame(
            $payload,
            gzdecode((string) $response->getBody()),
            'the wire answer carries the whole representation, not the gzip of the nothing the read left',
        );
        self::assertLessThan(
            4096,
            $response->getBody()->getSize(),
            'the compressed answer is smaller than the representation it still carries whole',
        );
    }

    /**
     * The control half of the reproduction: without the body-reading
     * middleware ahead of it, the compression family reads a
     * non-seekable body whole on its own -- the defect was always the
     * consumed stream flowing on, never the engine's own walk.
     */
    public function testTheCompressionFamilyAloneReadsANonSeekableBodyWhole(): void
    {
        $payload = str_repeat(self::PAYLOAD, 64);
        $response = $this->compression()->process(
            $this->request(),
            new FixedResponseHandler(new Response(200, [], new NonSeekableBody($payload))),
        );

        self::assertSame('gzip', $response->getHeaderLine('Content-Encoding'));
        self::assertSame($payload, gzdecode((string) $response->getBody()));
    }

    /**
     * The empty boundary through the same pipeline: an empty non-seekable
     * body rides onward as its known-empty snapshot, and the threshold
     * refuses it on the family's own terms -- no unknown-size admission,
     * no engine run over nothing.
     */
    public function testAnEmptyNonSeekableBodyThroughThePipelineEarnsNoEngine(): void
    {
        $response = $this->conditionalPipeline(new NonSeekableBody(''));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Content-Encoding'));
        self::assertFalse($response->hasHeader('ETag'));
        self::assertSame('', (string) $response->getBody());
    }

    /**
     * @param StreamInterface $body the handler's answer body
     */
    private function conditionalPipeline(StreamInterface $body): ResponseInterface
    {
        $conditional = new ETagMiddleware();

        return $conditional->process(
            $this->request(),
            new MiddlewareHandler(
                $this->compression(),
                new FixedResponseHandler(new Response(200, [], $body)),
            ),
        );
    }

    private function compression(): CompressionMiddleware
    {
        return new CompressionMiddleware(
            new CompressionPolicy(true, 1024, 6),
            new CompressionNegotiator(['gzip', 'deflate']),
        );
    }

    private function request(): ServerRequest
    {
        $message = (new ServerRequest('GET', new Uri('https://app.example.com/stream')))
            ->withHeader('Accept-Encoding', 'gzip');
        self::assertInstanceOf(ServerRequest::class, $message, 'the with* chain must preserve the request type.');

        return $message;
    }

    private function etagOf(string $body): string
    {
        return '"' . bin2hex(hash('sha256', $body, true)) . '"';
    }
}
