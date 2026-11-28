<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamOpener;
use Zef\Framework\Http\WorkerResponseBody;
use Zef\Test\Support\Http\ForeignResponse;
use Zef\Test\Support\Http\LyingEofStreamWrapper;
use Zef\Test\Support\Http\RecordingStream;
use Zef\Test\Support\Http\TellGuardedStream;
use Zef\Test\Support\Http\WriteOnlySeekableStreamWrapper;

/**
 * The worker answer's body, on its own terms: the payload rules that
 * decide whether the wire shape carries a body at all, and the read
 * that answers the resident string a worker runtime hands its server.
 *
 * @internal
 */
#[CoversClass(WorkerResponseBody::class)]
final class WorkerResponseBodyTest extends TestCase
{
    private const string LYING_EOF_PROTOCOL = 'zef-worker-response-lying-eof';

    private const string WRITE_ONLY_SEEKABLE_PROTOCOL = 'zef-worker-response-write-only-seekable';

    #[Override]
    protected function setUp(): void
    {
        stream_wrapper_register(self::LYING_EOF_PROTOCOL, LyingEofStreamWrapper::class);
        stream_wrapper_register(
            self::WRITE_ONLY_SEEKABLE_PROTOCOL,
            WriteOnlySeekableStreamWrapper::class,
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        stream_wrapper_unregister(self::LYING_EOF_PROTOCOL);
        stream_wrapper_unregister(self::WRITE_ONLY_SEEKABLE_PROTOCOL);
    }

    public function testTheBodylessStatusesAnswerNoBody(): void
    {
        foreach ([100, 101, 199, 204, 205, 304] as $status) {
            self::assertSame(
                '',
                WorkerResponseBody::read(new Response($status, [], 'must-not-appear')),
                "status {$status} must not carry a body",
            );
        }

        foreach ([200, 201, 404, 599] as $status) {
            self::assertSame(
                'payload',
                WorkerResponseBody::read(new Response($status, [], 'payload')),
                "status {$status} must carry its body",
            );
        }
    }

    public function testTheBodyIsReadWholeInFixedSizeChunks(): void
    {
        $payload = str_repeat('x', 20000);
        $recordingStream = new RecordingStream(Stream::fromString($payload));

        $body = WorkerResponseBody::read(new ForeignResponse(200, [], $recordingStream));

        self::assertSame($payload, $body);
        self::assertSame([8192, 8192, 8192], $recordingStream->readLengths);
    }

    public function testTheReadStartsAtTheCursorTheEmitterLeft(): void
    {
        $stream = Stream::fromString('hello');
        $stream->seek(2);

        self::assertSame(
            'llo',
            WorkerResponseBody::read(new ForeignResponse(200, [], $stream)),
            'the fold reads from wherever the emitter left the cursor, exactly as the SAPI twin does',
        );
    }

    public function testAnEmptyBodyAnswersEmptyThroughTheDefensiveEmptyRead(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString(''));

        self::assertSame('', WorkerResponseBody::read(new ForeignResponse(200, [], $recordingStream)));
        self::assertSame([8192], $recordingStream->readLengths);
    }

    public function testStopsAtAnEmptyReadBelowTheEndOfStream(): void
    {
        $stream = new Stream(StreamOpener::open(self::LYING_EOF_PROTOCOL . '://resource', 'r'));

        $body = WorkerResponseBody::read(new ForeignResponse(200, [], $stream));

        self::assertSame('AB', $body);
        self::assertFalse($stream->eof());
    }

    public function testANonSeekableBodyIsReadWithoutEverTouchingItsCursor(): void
    {
        // TellGuardedStream refuses tell() and rewind() loudly while its
        // read answers one empty chunk below a not-at-eof flag: the fold
        // never rewinds what the emitter cannot rewind, and the
        // defensive stop owns the answer.
        self::assertSame(
            '',
            WorkerResponseBody::read(new ForeignResponse(200, [], new TellGuardedStream(3))),
        );
    }

    public function testAnUnreadableBodyFailsClosed(): void
    {
        $stream = new Stream(StreamOpener::open(self::WRITE_ONLY_SEEKABLE_PROTOCOL . '://resource', 'wb'));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Response body stream is not readable.');

        WorkerResponseBody::read(new ForeignResponse(200, [], $stream));
    }
}
