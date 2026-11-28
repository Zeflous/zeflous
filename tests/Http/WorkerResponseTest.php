<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HeaderGrammar;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\HttpVersion;
use Zef\Framework\Http\WorkerHeaders;
use Zef\Framework\Http\WorkerResponse;

/**
 * The worker answer's wire shape, on its own terms: the constructor's
 * own judgments -- the inbound twin's doctrine, replayed on the
 * outbound side, every field refused before any runtime ever sees it.
 *
 * @internal
 */
#[CoversClass(WorkerResponse::class)]
#[CoversClass(WorkerHeaders::class)]
#[CoversClass(HeaderGrammar::class)]
#[CoversClass(HttpVersion::class)]
final class WorkerResponseTest extends TestCase
{
    public function testTheWireShapeCarriesEveryFieldItWasBuiltWith(): void
    {
        $workerResponse = new WorkerResponse(
            200,
            'OK',
            ['Content-Type' => 'application/json', 'X-Multi' => ['a', 'b']],
            '{"ok":true}',
            '1.1',
        );

        self::assertSame(200, $workerResponse->status);
        self::assertSame('OK', $workerResponse->reasonPhrase);
        self::assertSame(
            ['content-type' => ['application/json'], 'x-multi' => ['a', 'b']],
            $workerResponse->headers,
            'the header map answers the canonical form the worker lane owns both directions through',
        );
        self::assertSame('{"ok":true}', $workerResponse->body);
        self::assertSame('1.1', $workerResponse->protocolVersion);
    }

    public function testTheDefaultsAnswerTheBareAnswer(): void
    {
        $workerResponse = new WorkerResponse(204);

        self::assertSame('', $workerResponse->reasonPhrase);
        self::assertSame([], $workerResponse->headers);
        self::assertSame('', $workerResponse->body);
        self::assertSame('1.1', $workerResponse->protocolVersion);
    }

    public function testTheBodyRidesBinarySafe(): void
    {
        $payload = "\x00\x01 binary \xFF payload \u{2713}";

        $workerResponse = new WorkerResponse(200, body: $payload);

        self::assertSame(
            $payload,
            $workerResponse->body,
            'no length claim and no encoding: the payload\'s own bytes are the whole truth',
        );
    }

    public function testTheStatusCorridorHoldsBothWalls(): void
    {
        foreach ([100, 599] as $status) {
            self::assertSame($status, (new WorkerResponse($status))->status);
        }
    }

    #[DataProvider('provideAStatusOutsideTheCorridorIsRefusedCases')]
    public function testAStatusOutsideTheCorridorIsRefused(int $status): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains("Invalid HTTP status code: {$status}.");

        new WorkerResponse($status);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAStatusOutsideTheCorridorIsRefusedCases(): iterable
    {
        yield 'below the corridor' => [99];

        yield 'above the corridor' => [600];
    }

    #[DataProvider('provideAPhraseOutsideTheByteGrammarIsRefusedCases')]
    public function testAPhraseOutsideTheByteGrammarIsRefused(string $reasonPhrase): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid reason phrase.');

        new WorkerResponse(200, $reasonPhrase);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAPhraseOutsideTheByteGrammarIsRefusedCases(): iterable
    {
        yield 'a carriage return' => ["OK\r"];

        yield 'a line feed' => ["OK\n"];

        yield 'a null byte' => ["OK\0"];
    }

    public function testAHeaderMapTheGrammarRefusesIsRefusedAtConstruction(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Header value must be a string.');

        new WorkerResponse(200, headers: ['Content-Length' => 2048]);
    }

    #[DataProvider('provideAProtocolVersionOutsideTheGrammarIsRefusedCases')]
    public function testAProtocolVersionOutsideTheGrammarIsRefused(string $protocolVersion): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Invalid HTTP protocol version.');

        new WorkerResponse(200, protocolVersion: $protocolVersion);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAProtocolVersionOutsideTheGrammarIsRefusedCases(): iterable
    {
        yield 'the SAPI spelling' => ['HTTP/1.1'];

        yield 'empty' => [''];
    }

    public function testTheVersionsTheGrammarCarriesRideVerbatim(): void
    {
        foreach (['1.0', '1.1', '2', '3'] as $protocolVersion) {
            self::assertSame(
                $protocolVersion,
                (new WorkerResponse(200, protocolVersion: $protocolVersion))->protocolVersion,
            );
        }
    }
}
