<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\BodyBudget;
use Zef\Framework\Http\BodyTransfer;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\LimitedInputStream;
use Zef\Framework\Http\RequestBodyPolicy;
use Zef\Framework\Http\Stream;
use Zef\Test\Support\Http\CursoredStream;
use Zef\Test\Support\Http\NeverEndingEofStream;
use Zef\Test\Support\Http\RecordingStream;

/**
 * @internal
 */
#[CoversClass(LimitedInputStream::class)]
#[CoversClass(BodyBudget::class)]
#[CoversClass(BodyTransfer::class)]
#[CoversClass(RequestBodyPolicy::class)]
#[CoversClass(Stream::class)]
final class LimitedInputStreamTest extends TestCase
{
    private const string BODY_TOO_LARGE = 'Request body exceeds configured size limit.';

    public function testCapabilitiesAnswerTheInnerStream(): void
    {
        $limited = $this->limited('body-bytes', 100);

        self::assertTrue($limited->isReadable());
        self::assertTrue($limited->isSeekable());
        self::assertFalse($limited->eof());
        self::assertSame(0, $limited->tell());
    }

    public function testMetadataAnswersTheInnerStream(): void
    {
        $limited = $this->limited('body-bytes', 100);

        self::assertIsArray($limited->getMetadata());
        self::assertIsString($limited->getMetadata('uri'));
        self::assertNull($limited->getMetadata('no-such-key'));
    }

    public function testTheWrapperIsNeverWritable(): void
    {
        $limited = $this->limited('body-bytes', 100);

        self::assertFalse($limited->isWritable());
    }

    public function testWriteIsRejectedFailClosed(): void
    {
        $limited = $this->limited('body-bytes', 100);

        $this->assertThrows(
            'Limited input stream is read-only.',
            static function () use ($limited): void {
                $limited->write('anything');
            },
        );
    }

    public function testCloseClosesTheInnerStream(): void
    {
        $inner = Stream::fromString('body-bytes');
        $limitedInputStream = new LimitedInputStream($inner, new RequestBodyPolicy(100));

        $limitedInputStream->close();

        self::assertFalse($inner->isReadable());
    }

    public function testDetachAnswersTheInnerResource(): void
    {
        $inner = Stream::fromString('body-bytes');
        $limitedInputStream = new LimitedInputStream($inner, new RequestBodyPolicy(100));

        $resource = $limitedInputStream->detach();

        self::assertIsResource($resource);
        self::assertFalse($inner->isReadable());
    }

    public function testReadRejectsANegativeLength(): void
    {
        $limited = $this->limited('body-bytes', 100);

        $this->assertThrows(
            'Length must be non-negative.',
            static function () use ($limited): void {
                $limited->read(-1);
            },
        );
    }

    /**
     * The wrapper's own guard fires before the inner stream is consulted
     * at all: the journal is the only witness that separates the wrapper's
     * rejection from an inner stream that happens to validate negative
     * lengths with the same message.
     */
    public function testReadRejectsANegativeLengthBeforeConsultingTheInnerStream(): void
    {
        $neverEndingEofStream = new NeverEndingEofStream('abc');
        $limitedInputStream = new LimitedInputStream($neverEndingEofStream, new RequestBodyPolicy(10));

        $this->assertThrows(
            'Length must be non-negative.',
            static function () use ($limitedInputStream): void {
                $limitedInputStream->read(-1);
            },
        );

        self::assertSame([], $neverEndingEofStream->readLengths);
    }

    /**
     * A zero-length read answers nothing and must not even ask the inner
     * stream for bytes: the journal is the only witness that separates a
     * genuine short-circuit from a probe that happens to return ''.
     */
    public function testAZeroLengthReadConsultsNothing(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('body-bytes'));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(100));

        self::assertSame('', $limitedInputStream->read(0));
        self::assertSame([], $recordingStream->readLengths);
    }

    public function testReadDeliversTheInnerBytesItAskedFor(): void
    {
        $limited = $this->limited('abcdefghij', 100);

        self::assertSame('abc', $limited->read(3));
        self::assertSame('def', $limited->read(3));
        self::assertSame(6, $limited->tell());
    }

    public function testAShortInnerReadIsCompliant(): void
    {
        $limited = $this->limited('abc', 100);

        self::assertSame('abc', $limited->read(10));
    }

    public function testReadingExactlyToThePolicyLimitIsCompliant(): void
    {
        $limited = $this->limited('abcde', 5);

        self::assertSame('abcde', $limited->read(5));
        self::assertSame('', $limited->read(1));
    }

    public function testReadPastThePolicyLimitFailsClosed(): void
    {
        $limited = $this->limited('abcdef', 5);

        $this->assertThrows(
            self::BODY_TOO_LARGE,
            static function () use ($limited): void {
                $limited->read(100);
            },
        );
    }

    public function testGetContentsPastThePolicyLimitFailsClosed(): void
    {
        $limited = $this->limited('abcdefgh', 5);

        $this->assertThrows(
            self::BODY_TOO_LARGE,
            static function () use ($limited): void {
                $limited->getContents();
            },
        );
    }

    /**
     * The overflow failure is sticky: the counter is poisoned past the
     * limit, so a later read refuses even when the inner stream is already
     * exhausted and answers nothing at all.
     */
    public function testTheOverflowFailureIsSticky(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('abcdef'));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(5));

        try {
            $limitedInputStream->read(100);
            self::fail('The first read past the limit must fail closed.');
        } catch (HttpException) {
            // fall through to the sticky assertion
        }

        try {
            $limitedInputStream->read(2);
            self::fail('A read after an overflow must keep failing.');
        } catch (HttpException) {
            // the sticky failure is the expectation
        }

        self::assertSame([6, 1], $recordingStream->readLengths);
    }

    /**
     * The probe asks for at most one byte past the remaining budget: that
     * single extra byte is what separates "exactly at the limit" from
     * "past the limit" without ever buffering more than the policy allows.
     */
    public function testTheProbeAsksAtMostOneBytePastTheBudget(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('abcdefghij'));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(10));

        $limitedInputStream->read(8);
        $limitedInputStream->read(8);

        self::assertSame([8, 3], $recordingStream->readLengths);
    }

    public function testMultipleReadsAccumulateUnderThePolicy(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('abcdefghij'));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(10));

        self::assertSame('abcd', $limitedInputStream->read(4));
        self::assertSame('efgh', $limitedInputStream->read(4));
        self::assertSame('ij', $limitedInputStream->read(4));

        self::assertSame([4, 4, 3], $recordingStream->readLengths);
    }

    public function testReadPropagatesTheInnerFailure(): void
    {
        $inner = Stream::fromString('body-bytes');
        $limitedInputStream = new LimitedInputStream($inner, new RequestBodyPolicy(100));
        $inner->close();

        $this->assertThrows(
            'Stream is not readable.',
            static function () use ($limitedInputStream): void {
                $limitedInputStream->read(4);
            },
        );
    }

    public function testGetSizeAnswersTheEffectiveSize(): void
    {
        self::assertSame(5, $this->limited('abcdefghij', 5)->getSize());
        self::assertSame(3, $this->limited('abc', 5)->getSize());
        self::assertSame(10, $this->limited('0123456789', 100)->getSize());
    }

    public function testGetSizeAnswersNullWhenTheInnerStreamIsUnmeasurable(): void
    {
        $limited = $this->limited('body-bytes', 100);
        $limited->detach();

        self::assertNull($limited->getSize());
    }

    public function testRewindRebasesTheObservedCount(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('abcde'));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(5));

        self::assertSame('abcde', $limitedInputStream->read(5));
        $limitedInputStream->rewind();

        self::assertSame(0, $limitedInputStream->tell());
        self::assertSame('abcde', $limitedInputStream->read(100));
        self::assertSame([5, 6], $recordingStream->readLengths);
    }

    public function testSeekRebasesTheObservedCountOnTheNewPosition(): void
    {
        $limited = $this->limited('abcdefgh', 8);

        self::assertSame('abcd', $limited->read(4));
        $limited->seek(2);

        self::assertSame(2, $limited->tell());
        self::assertSame('cdefg', $limited->read(5));
    }

    /**
     * A re-seek to the beginning re-bases the count from an honest zero:
     * the probe then asks for one byte past the whole budget, and a body
     * that is exactly the limit drains compliantly.
     */
    public function testSeekToTheBeginningRebasesTheCountFromZero(): void
    {
        $limited = $this->limited('abcde', 5);

        self::assertSame('abcde', $limited->read(5));
        $limited->seek(0);

        self::assertSame(0, $limited->tell());
        self::assertSame('abcde', $limited->read(100));
    }

    /**
     * The clamp on the inner stream's answered position is the wrapper's
     * fail-closed posture against an implementation that answers a
     * negative cursor: the count re-bases from zero, never from the
     * negative answer -- the adversarial position the {@see CursoredStream}
     * double pins for the emitter's reconciliation, reused here through
     * the read-length journal.
     */
    public function testSeekClampsANegativeAnsweredPositionToZero(): void
    {
        $recordingStream = new RecordingStream(new CursoredStream(null, -5));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(5));

        $limitedInputStream->seek(0);

        self::assertSame('', $limitedInputStream->read(100));
        self::assertSame([6], $recordingStream->readLengths);
    }

    public function testGetContentsDrainsTheBodyUnderThePolicy(): void
    {
        $body = str_repeat('a', 20000);
        $recordingStream = new RecordingStream(Stream::fromString($body));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(100000));

        self::assertSame($body, $limitedInputStream->getContents());
        self::assertSame([8192, 8192, 8192], $recordingStream->readLengths);
    }

    /**
     * A drain that lands exactly on the policy limit is compliant, and the
     * final probe shrinks to the remaining budget plus one -- the journal
     * pins both the chunk size and the probe's arithmetic.
     */
    public function testADrainLandingExactlyOnTheLimitIsCompliant(): void
    {
        $body = str_repeat('a', 20000);
        $recordingStream = new RecordingStream(Stream::fromString($body));
        $limitedInputStream = new LimitedInputStream($recordingStream, new RequestBodyPolicy(20000));

        self::assertSame($body, $limitedInputStream->getContents());
        self::assertSame([8192, 8192, 3617], $recordingStream->readLengths);
    }

    public function testGetContentsAnswersNothingForAnEmptyBody(): void
    {
        $limited = $this->limited('', 100);

        self::assertSame('', $limited->getContents());
    }

    /**
     * The drain's defensive break on the empty chunk is the only way out
     * for a stream whose end-of-file answer is a lie that never flips.
     */
    public function testGetContentsTerminatesForALyingEndOfFile(): void
    {
        $neverEndingEofStream = new NeverEndingEofStream('abcd');
        $limitedInputStream = new LimitedInputStream($neverEndingEofStream, new RequestBodyPolicy(100));

        self::assertSame('abcd', $limitedInputStream->getContents());
        self::assertSame([101, 97], $neverEndingEofStream->readLengths);
    }

    public function testToStringAnswersTheBodyUnderThePolicy(): void
    {
        $limited = $this->limited('hello', 100);

        self::assertSame('hello', (string) $limited);
    }

    /**
     * PSR-7: __toString() must always answer a string, including when the
     * policy refuses the body -- the failure is reported as ''.
     */
    public function testToStringAnswersNothingWhenThePolicyRefuses(): void
    {
        $limited = $this->limited('abcdefgh', 2);

        self::assertSame('', (string) $limited);
    }

    /**
     * @return LimitedInputStream the policy-wrapped body over a temp stream
     */
    private function limited(string $body, int $maxBytes): LimitedInputStream
    {
        return new LimitedInputStream(
            Stream::fromString($body),
            new RequestBodyPolicy($maxBytes),
        );
    }

    /**
     * @param callable(): void $operation
     */
    private function assertThrows(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
