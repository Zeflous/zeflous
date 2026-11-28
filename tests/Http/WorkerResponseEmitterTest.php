<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ContentLength;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpStatusLine;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\StatusFraming;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\WorkerResponse;
use Zef\Framework\Http\WorkerResponseEmitter;
use Zef\Framework\Http\WorkerResponseHeaders;
use Zef\Framework\Http\WorkerResponseBody;
use Zef\Test\Support\Http\CursoredStream;
use Zef\Test\Support\Http\ForeignResponse;
use Zef\Test\Support\Http\TellGuardedStream;

/**
 * The worker half of the runtime adapter, outbound: one composition
 * every worker runtime funnels out through -- the application's
 * response becoming the wire shape the deployment's glue hands its
 * server, the SAPI emitter's ordering and safeguards replayed on a
 * payload no SAPI ever touches.
 *
 * @internal
 */
#[CoversClass(WorkerResponseEmitter::class)]
#[CoversClass(WorkerResponse::class)]
#[CoversClass(WorkerResponseHeaders::class)]
#[CoversClass(WorkerResponseBody::class)]
#[CoversClass(HttpStatusLine::class)]
#[CoversClass(StatusFraming::class)]
#[CoversClass(ContentLength::class)]
#[CoversClass(HeaderGrammar::class)]
final class WorkerResponseEmitterTest extends TestCase
{
    public function testTheResponseBecomesTheOutboundPayload(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json', 'X-Multi' => ['a', 'b']],
            '{"ok":true}',
        );

        $workerResponse = (new WorkerResponseEmitter())->toWorker($response);

        self::assertSame(200, $workerResponse->status);
        self::assertSame('OK', $workerResponse->reasonPhrase);
        self::assertSame(
            ['content-type' => ['application/json'], 'x-multi' => ['a', 'b']],
            $workerResponse->headers,
            'the wire shape answers the canonical header form the worker lane owns',
        );
        self::assertSame('{"ok":true}', $workerResponse->body);
        self::assertSame('1.1', $workerResponse->protocolVersion);
    }

    public function testAForeignPhraseRidesTheWireShape(): void
    {
        $response = new ForeignResponse(200, [], Stream::fromString('x'), 'Custom OK');

        $workerResponse = (new WorkerResponseEmitter())->toWorker($response);

        self::assertSame('Custom OK', $workerResponse->reasonPhrase);
    }

    public function testAPhraseOutsideTheByteGrammarAnswersTheRegisteredPhrase(): void
    {
        $response = new ForeignResponse(200, [], Stream::fromString('x'), "Bad\r\nPhrase");

        $workerResponse = (new WorkerResponseEmitter())->toWorker($response);

        self::assertSame(
            'OK',
            $workerResponse->reasonPhrase,
            'the attacker-shaped phrase never rides the wire: the registered phrase answers',
        );
    }

    public function testAnUnacceptablePhraseWithoutARegistryEntryAnswersEmpty(): void
    {
        $response = new ForeignResponse(599, [], Stream::fromString('x'), "Bad\0Phrase");

        $workerResponse = (new WorkerResponseEmitter())->toWorker($response);

        self::assertSame('', $workerResponse->reasonPhrase);
    }

    #[DataProvider('provideAForeignStatusOutsideTheCorridorIsRefusedFailClosedCases')]
    public function testAForeignStatusOutsideTheCorridorIsRefusedFailClosed(int $status): void
    {
        $response = new ForeignResponse($status, [], Stream::fromString('x'));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains("Invalid HTTP status code: {$status}.");

        (new WorkerResponseEmitter())->toWorker($response);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAForeignStatusOutsideTheCorridorIsRefusedFailClosedCases(): iterable
    {
        yield 'above the corridor' => [999];

        yield 'below the corridor' => [0];
    }

    public function testRewindsADrainedSeekableBodyBeforeReading(): void
    {
        $body = Stream::fromString('hello');
        self::assertSame('hello', (string) $body);
        self::assertTrue($body->eof());

        $workerResponse = (new WorkerResponseEmitter())->toWorker(new Response(200, [], $body));

        self::assertSame('hello', $workerResponse->body);
    }

    public function testLeavesANonSeekableBodyUntouched(): void
    {
        // TellGuardedStream refuses rewind() loudly: the guarded stream
        // is the observation that the emitter never rewinds a body it
        // cannot rewind, and the defensive empty read owns the answer.
        $workerResponse = (new WorkerResponseEmitter())->toWorker(
            new ForeignResponse(200, [], new TellGuardedStream(3)),
        );

        self::assertSame('', $workerResponse->body);
    }

    public function testTheBodylessStatusesAnswerNoBody(): void
    {
        foreach ([100, 101, 204, 205, 304] as $status) {
            $workerResponse = (new WorkerResponseEmitter())->toWorker(
                new Response($status, [], 'must-not-appear'),
            );

            self::assertSame('', $workerResponse->body, "status {$status} must not carry a body");
        }
    }

    public function testKeepsATruthfulContentLengthOnTheWireShape(): void
    {
        $workerResponse = (new WorkerResponseEmitter())->toWorker(
            new Response(200, ['Content-Length' => '3'], 'abc'),
        );

        self::assertSame(['3'], $workerResponse->headers['content-length']);
    }

    public function testDropsALyingContentLengthFromTheWireShape(): void
    {
        $workerResponse = (new WorkerResponseEmitter())->toWorker(
            new Response(200, ['Content-Length' => '999'], 'abc'),
        );

        self::assertArrayNotHasKey('content-length', $workerResponse->headers);
    }

    public function testDropsTheContentLengthOnTheStatusesThatMustNotCarryIt(): void
    {
        foreach ([100, 101, 204] as $status) {
            $workerResponse = (new WorkerResponseEmitter())->toWorker(
                new Response($status, ['Content-Length' => '0', 'X-Framework' => 'zef'], ''),
            );

            self::assertArrayNotHasKey(
                'content-length',
                $workerResponse->headers,
                "status {$status} must not carry a Content-Length",
            );
            self::assertSame(['zef'], $workerResponse->headers['x-framework']);
        }
    }

    public function testAnUnmeasurableCursorLosesItsDeclaredContentLength(): void
    {
        $workerResponse = (new WorkerResponseEmitter())->toWorker(
            new ForeignResponse(200, ['Content-Length' => ['3']], new CursoredStream(null, 0)),
        );

        self::assertArrayNotHasKey('content-length', $workerResponse->headers);
    }

    public function testSkipsInvalidFieldsAndKeepsTheValidNeighbours(): void
    {
        $response = new ForeignResponse(
            200,
            ['Bad Name' => ['v'], 'X-Good' => ['ok'], 'X-Bad-Value' => ["line\nbreak"]],
            Stream::fromString('x'),
        )
            ->withHeader('X-Also', 'fine')
            ->withHeader('X-Count', 5)
        ;

        $workerResponse = (new WorkerResponseEmitter())->toWorker($response);

        self::assertSame(
            ['x-also' => ['fine'], 'x-good' => ['ok']],
            $workerResponse->headers,
        );
    }

    public function testTheNameFoldsThroughTheFullUnicodeCaseMapOnTheEmitterPath(): void
    {
        $response = new ForeignResponse(200, ["\u{212A}EY" => 'v'], Stream::fromString('x'));

        $workerResponse = (new WorkerResponseEmitter())->toWorker($response);

        self::assertSame(['key' => ['v']], $workerResponse->headers);
    }

    public function testAFieldWhoseNameIsAnEngineIntegerKeyRidesTheWireShape(): void
    {
        $mutated = (new Response(200, [], 'x'))->withHeader('123', 'v');
        self::assertInstanceOf(Response::class, $mutated);

        $workerResponse = (new WorkerResponseEmitter())->toWorker($mutated);

        self::assertSame(['123' => ['v']], $workerResponse->headers);
    }

    public function testTheProtocolVersionRidesTheWireShape(): void
    {
        $mutated = (new Response(200, [], 'x'))->withProtocolVersion('2');
        self::assertInstanceOf(Response::class, $mutated);

        $workerResponse = (new WorkerResponseEmitter())->toWorker($mutated);

        self::assertSame('2', $workerResponse->protocolVersion);
    }
}
