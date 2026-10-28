<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\PayloadTooLargeException;
use Zef\Framework\Http\RequestBodyPolicy;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamOpener;
use Zef\Framework\Http\WorkerBody;

/**
 * The payload-to-shim converter, on its own terms: the memory-resident
 * stream (never a temp file), the total budget judgment a payload that
 * states its own size buys, and both directions a lying claim can take.
 *
 * @internal
 */
#[CoversClass(WorkerBody::class)]
#[CoversClass(Stream::class)]
#[CoversClass(StreamOpener::class)]
final class WorkerBodyTest extends TestCase
{
    public function testThePayloadBecomesARewoundReadableMemoryStream(): void
    {
        $stream = WorkerBody::bounded('{"page":2}', '10', new RequestBodyPolicy());

        self::assertSame('{"page":2}', $stream->getContents(), 'the payload rides the stream verbatim');
        self::assertSame(10, $stream->getSize(), "the stream states the payload's own byte length");
        self::assertTrue($stream->isReadable(), 'the body is readable for the decode downstream');
        self::assertTrue($stream->isSeekable(), 'the body is seekable for the snapshot restores');
        $stream->rewind();
        self::assertSame('{"page":2}', $stream->getContents(), 'a second read after rewind answers the same payload');
    }

    public function testABinaryPayloadRoundTripsUntouched(): void
    {
        $payload = "\x00\x01\xFF\xFE" . str_repeat('A', 64) . "\r\n";

        $stream = WorkerBody::bounded($payload, '', new RequestBodyPolicy());

        self::assertSame($payload, $stream->getContents(), 'binary-safe: every byte of the payload survives');
    }

    public function testAnEmptyPayloadAnswersTheEmptyStream(): void
    {
        $stream = WorkerBody::bounded('', '', new RequestBodyPolicy());

        self::assertSame('', $stream->getContents(), 'an empty payload is an empty body');
        self::assertSame(0, $stream->getSize());
    }

    public function testAnInflatedClaimFailsFast(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        WorkerBody::bounded('{"page":2}', '104857600', new RequestBodyPolicy(maxBytes: 16));
    }

    public function testAnOverBudgetPayloadIsRefusedWithoutAnyClaim(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        WorkerBody::bounded(str_repeat('A', 17), '', new RequestBodyPolicy(maxBytes: 16));
    }

    public function testADeflatedClaimCannotSmuggleAnOverBudgetPayload(): void
    {
        $this->expectException(PayloadTooLargeException::class);

        WorkerBody::bounded(str_repeat('A', 17), '1', new RequestBodyPolicy(maxBytes: 16));
    }

    public function testAPayloadExactlyAtTheBudgetRides(): void
    {
        $stream = WorkerBody::bounded(str_repeat('A', 16), '16', new RequestBodyPolicy(maxBytes: 16));

        self::assertSame(16, $stream->getSize(), 'the budget is a ceiling, not a fence below it');
    }

    public function testTheBudgetCountsBytesNotCharacters(): void
    {
        $multibyte = str_repeat("\u{00E9}", 9);

        $this->expectException(PayloadTooLargeException::class);

        WorkerBody::bounded($multibyte, '', new RequestBodyPolicy(maxBytes: 16));
    }

    public function testTheMemoryResidentPostureHoldsEvenPastTheTempThreshold(): void
    {
        $payload = str_repeat('B', 2 * 1024 * 1024 + 10);
        $requestBodyPolicy = new RequestBodyPolicy(maxBytes: 4 * 1024 * 1024);

        $stream = WorkerBody::bounded($payload, (string) mb_strlen($payload, '8bit'), $requestBodyPolicy);

        self::assertSame(
            mb_strlen($payload, '8bit'),
            $stream->getSize(),
            'a payload inside the policy rides memory-resident, never a temp file',
        );
        $uri = $stream->getMetadata('uri');
        self::assertIsString($uri);
        self::assertSame('php://memory', $uri, 'the stream the fold opens is the memory wrapper, not php://temp');
    }
}
