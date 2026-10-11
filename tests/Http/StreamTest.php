<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamMetadata;
use Zef\Framework\Http\StreamOpener;
use Zef\Framework\Http\StreamTransfer;
use Zef\Test\Support\Http\FailingStreamWrapper;
use Zef\Test\Support\Http\NoSeekStreamWrapper;
use Zef\Test\Support\Http\ThrowingStreamWrapper;
use Zef\Test\Support\Http\WriteOnlySeekableStreamWrapper;

/**
 * @internal
 */
#[CoversClass(Stream::class)]
#[CoversClass(StreamOpener::class)]
#[CoversClass(StreamMetadata::class)]
#[CoversClass(StreamTransfer::class)]
final class StreamTest extends TestCase
{
    private const string FAILING_PROTOCOL = 'zef-failing-read-write';

    private const string NO_SEEK_PROTOCOL = 'zef-no-seek';

    private const string THROWING_PROTOCOL = 'zef-throwing-open';

    private const string WRITE_ONLY_SEEKABLE_PROTOCOL = 'zef-write-only-seekable';

    #[Override]
    protected function setUp(): void
    {
        stream_wrapper_register(self::FAILING_PROTOCOL, FailingStreamWrapper::class);
        stream_wrapper_register(self::NO_SEEK_PROTOCOL, NoSeekStreamWrapper::class);
        stream_wrapper_register(self::THROWING_PROTOCOL, ThrowingStreamWrapper::class);
        stream_wrapper_register(
            self::WRITE_ONLY_SEEKABLE_PROTOCOL,
            WriteOnlySeekableStreamWrapper::class,
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        stream_wrapper_unregister(self::FAILING_PROTOCOL);
        stream_wrapper_unregister(self::NO_SEEK_PROTOCOL);
        stream_wrapper_unregister(self::THROWING_PROTOCOL);
        stream_wrapper_unregister(self::WRITE_ONLY_SEEKABLE_PROTOCOL);
    }

    public function testConstructorRejectsAnythingThatIsNotAResource(): void
    {
        $this->assertThrows('Stream resource required.', static function (): void {
            new Stream('not-a-resource');
        });
    }

    public function testReadWriteSeekCycle(): void
    {
        $stream = $this->memoryStream();

        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame(4, $stream->write('abcd'));
        self::assertSame(4, $stream->tell());
        $stream->seek(2);
        self::assertSame(2, $stream->tell());
        self::assertSame('cd', $stream->read(2));
        $stream->seek(2);
        self::assertSame('cd', $stream->getContents());
        $stream->rewind();
        self::assertSame(0, $stream->tell());
        self::assertSame('abcd', (string) $stream);
        self::assertSame(4, $stream->getSize());
        self::assertSame('PHP', $stream->getMetadata('wrapper_type'));
        self::assertIsArray($stream->getMetadata());

        $stream->seek(0);
        self::assertFalse($stream->eof());
        // Reading one byte MORE than available trips feof() -- reading the
        // exact remaining count would not (PHP feof semantics).
        $stream->read(5);
        self::assertTrue($stream->eof());
    }

    public function testReadZeroReturnsAnEmptyStringWithoutMovingThePosition(): void
    {
        $stream = Stream::fromString('ab');
        $stream->seek(1);

        self::assertSame('', $stream->read(0));
        self::assertSame(1, $stream->tell());
    }

    public function testReadRejectsNegativeLength(): void
    {
        $this->assertThrows('Length must be non-negative.', static function (): void {
            Stream::fromString('ab')->read(-1);
        });
    }

    public function testReadReturnsTheRemainingBytesWhenFewerExist(): void
    {
        $stream = Stream::fromString('hello');

        self::assertSame('hello', $stream->read(10));
    }

    public function testReadThrowsWhenTheStreamIsNotReadable(): void
    {
        $stream = $this->stdoutStream();
        self::assertFalse($stream->isReadable());

        $this->assertThrows('Stream is not readable.', static function () use ($stream): void {
            $stream->read(4);
        });
    }

    public function testReadThrowsWhenTheEngineReadFails(): void
    {
        $stream = $this->failingStream();

        $this->assertThrows('Unable to read stream.', static function () use ($stream): void {
            $stream->read(3);
        });
    }

    public function testWriteThrowsWhenTheStreamIsNotWritable(): void
    {
        $stream = $this->memoryStream('rb');
        self::assertFalse($stream->isWritable());

        $this->assertThrows('Stream is not writable.', static function () use ($stream): void {
            $stream->write('nope');
        });
    }

    public function testWriteThrowsWhenTheEngineWriteFails(): void
    {
        $stream = $this->failingStream();

        $this->assertThrows('Unable to write to stream.', static function () use ($stream): void {
            $stream->write('nope');
        });
    }

    public function testTellThrowsWhenTheStreamIsDetached(): void
    {
        $stream = Stream::fromString('payload');
        $stream->detach();

        $this->assertThrows('Stream detached.', static function () use ($stream): void {
            $stream->tell();
        });
    }

    public function testTellThrowsWhenThePositionCannotBeDetermined(): void
    {
        $stream = $this->stdoutStream();

        $this->assertThrows('Unable to determine stream position.', static function () use ($stream): void {
            $stream->tell();
        });
    }

    public function testSeekThrowsWhenTheStreamIsDetached(): void
    {
        $stream = Stream::fromString('payload');
        $stream->detach();

        $this->assertThrows('Stream is not seekable.', static function () use ($stream): void {
            $stream->seek(0);
        });
    }

    public function testSeekThrowsWhenTheStreamIsNotSeekable(): void
    {
        $stream = $this->outputStream();
        self::assertFalse($stream->isSeekable());

        $this->assertThrows('Stream is not seekable.', static function () use ($stream): void {
            $stream->seek(0);
        });
    }

    public function testSeekThrowsWhenTheEngineSeekFails(): void
    {
        $stream = $this->noSeekStream();
        self::assertTrue($stream->isSeekable());

        set_error_handler(static fn (): bool => true);

        try {
            $stream->seek(0);
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame('Stream is not seekable.', $httpException->getMessage());
        } finally {
            restore_error_handler();
        }
    }

    public function testSeekHonoursTheCurrentPositionWhence(): void
    {
        $stream = Stream::fromString('abcdef');
        $stream->seek(1);
        $stream->seek(2, \SEEK_CUR);

        self::assertSame(3, $stream->tell());
        self::assertSame('def', $stream->getContents());
    }

    public function testRewindRestartsReadingFromTheBeginning(): void
    {
        $stream = Stream::fromString('abcd');
        $stream->seek(2);
        self::assertSame('cd', $stream->read(2));

        $stream->rewind();
        self::assertSame('abcd', $stream->getContents());
    }

    public function testEofIsTrueWhenTheStreamIsDetached(): void
    {
        $stream = Stream::fromString('payload');
        $stream->detach();

        self::assertTrue($stream->eof());
    }

    public function testGetSizeReturnsNullWhenTheStreamIsDetached(): void
    {
        $stream = Stream::fromString('payload');
        $stream->detach();

        self::assertNull($stream->getSize());
    }

    public function testGetSizeAnswersTheEngineStatisticAndToleratesSizelessOnes(): void
    {
        self::assertSame(7, Stream::fromString('payload')->getSize());
        self::assertNull($this->failingStream()->getSize());
    }

    public function testCapabilitiesOfADetachedStreamAreAllFalse(): void
    {
        $stream = Stream::fromString('payload');
        $stream->detach();

        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
    }

    public function testReadOnlyStreamCapabilities(): void
    {
        $stream = $this->memoryStream('rb');

        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertTrue($stream->isSeekable());
    }

    public function testWriteOnlyStreamCapabilities(): void
    {
        $stream = $this->stdoutStream();

        self::assertFalse($stream->isReadable());
        self::assertTrue($stream->isWritable());
    }

    public function testNonSeekableStreamCapabilities(): void
    {
        $stream = $this->outputStream();

        self::assertFalse($stream->isSeekable());
        self::assertTrue($stream->isWritable());
    }

    public function testMetadataReturnsTheFullMapAndSingleKeys(): void
    {
        $stream = $this->memoryStream();

        self::assertSame('PHP', $stream->getMetadata('wrapper_type'));
        self::assertNull($stream->getMetadata('no-such-key'));
        $full = $stream->getMetadata();
        self::assertIsArray($full);
        self::assertArrayHasKey('mode', $full);
    }

    public function testMetadataAnswersEmptyWhenTheStreamIsDetached(): void
    {
        $stream = Stream::fromString('payload');
        $stream->detach();

        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('mode'));
    }

    public function testToStringReturnsTheWholeContentsFromAnyPosition(): void
    {
        $stream = Stream::fromString('hello');
        $stream->seek(3);

        self::assertSame('hello', (string) $stream);
    }

    public function testToStringOnAClosedStreamIsEmpty(): void
    {
        $stream = Stream::fromString('payload');
        $stream->close();

        self::assertSame('', (string) $stream);
        self::assertNull($stream->getSize());
    }

    public function testToStringOnAnUnreadableStreamIsEmpty(): void
    {
        $stream = $this->stdoutStream();

        self::assertSame('', (string) $stream);
    }

    public function testToStringOnASeekableUnreadableStreamKeepsThePosition(): void
    {
        $stream = new Stream(fopen(self::WRITE_ONLY_SEEKABLE_PROTOCOL . '://resource', 'wb'));
        $stream->seek(2);

        self::assertSame('', (string) $stream);
        self::assertSame(2, $stream->tell());
    }

    public function testToStringSwallowsFailuresOfTheUnderlyingStream(): void
    {
        $stream = $this->noSeekStream();

        set_error_handler(static fn (): bool => true);

        try {
            self::assertSame('', (string) $stream);
        } finally {
            restore_error_handler();
        }
    }

    public function testCloseClosesTheUnderlyingResource(): void
    {
        $resource = fopen('php://memory', 'w+b');
        $stream = new Stream($resource);
        $stream->close();

        self::assertFalse(\is_resource($resource));
    }

    public function testCloseReleasesTheResourceAndLaterStateCallsFail(): void
    {
        $stream = Stream::fromString('payload');
        $stream->close();
        $stream->close();

        self::assertTrue($stream->eof());
        $this->assertThrows('Stream detached.', static function () use ($stream): void {
            $stream->tell();
        });
    }

    public function testDetachReturnsTheResourceAndEmptiesTheStream(): void
    {
        $stream = Stream::fromString('payload');
        $resource = $stream->detach();

        self::assertIsResource($resource);
        fclose($resource);
        self::assertNull($stream->detach());
        $this->assertThrows('Stream is not readable.', static function () use ($stream): void {
            $stream->read(1);
        });
    }

    public function testGetContentsReturnsOnlyTheRemainder(): void
    {
        $stream = Stream::fromString('abcdef');
        $stream->seek(2);

        self::assertSame('cdef', $stream->getContents());
    }

    public function testGetContentsThrowsWhenTheStreamIsDetached(): void
    {
        $stream = Stream::fromString('payload');
        $stream->detach();

        $this->assertThrows('Stream is not readable.', static function () use ($stream): void {
            $stream->getContents();
        });
    }

    public function testGetContentsThrowsWhenTheStreamIsNotReadable(): void
    {
        $stream = $this->stdoutStream();

        $this->assertThrows('Stream is not readable.', static function () use ($stream): void {
            $stream->getContents();
        });
    }

    public function testGetContentsDrainsAWrappedStreamUntilItsEof(): void
    {
        self::assertSame('data', $this->noSeekStream()->getContents());
    }

    public function testFromStringProducesASeekableReadableWritableStream(): void
    {
        $stream = Stream::fromString('payload');

        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame(0, $stream->tell());
        self::assertSame(7, $stream->getSize());
        self::assertSame('payload', $stream->getContents());
    }

    public function testFromStringOfEmptyContentYieldsAnEmptyStream(): void
    {
        $stream = Stream::fromString('');

        self::assertSame(0, $stream->getSize());
        self::assertSame(0, $stream->tell());
        self::assertSame('', $stream->read(1));
        self::assertTrue($stream->eof());
    }

    public function testOpenThrowsWhenTheResourceCannotBeOpened(): void
    {
        $path = '/zef-does-not-exist-' . bin2hex(random_bytes(4)) . '/stream.php';

        try {
            StreamOpener::open($path, 'rb');
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertStringStartsWith('Unable to open stream ' . $path, $httpException->getMessage());
            self::assertStringContainsString($path . ': ', $httpException->getMessage());
            self::assertStringContainsString('Failed to open stream', $httpException->getMessage());
        }
    }

    public function testOpenSuppressesTheEngineDiagnosticsWithinItsScope(): void
    {
        $path = '/zef-does-not-exist-' . bin2hex(random_bytes(4)) . '/stream.php';
        $outerScopeSawDiagnostic = false;
        set_error_handler(static function () use (&$outerScopeSawDiagnostic): bool {
            $outerScopeSawDiagnostic = true;

            return true;
        });

        try {
            try {
                StreamOpener::open($path, 'rb');
                self::fail('HttpException expected.');
            } catch (HttpException) {
                // The open's own failure -- the probe is what is asserted.
            }
        } finally {
            restore_error_handler();
        }

        self::assertFalse($outerScopeSawDiagnostic);
    }

    public function testOpenRestoresTheErrorHandlerWhenTheEngineThrows(): void
    {
        $probeSawDiagnostic = false;
        set_error_handler(static function () use (&$probeSawDiagnostic): bool {
            $probeSawDiagnostic = true;

            return true;
        });

        try {
            try {
                StreamOpener::open(self::THROWING_PROTOCOL . '://resource', 'r');
                self::fail('The wrapper failure was expected to propagate.');
            } catch (RuntimeException) {
                // The engine-level wrapper failure propagates unchanged.
            }

            // With the opener's handler restored, the probe owns the stack
            // again and answers the diagnostic itself.
            trigger_error('probe', \E_USER_WARNING);
        } finally {
            restore_error_handler();
        }

        self::assertTrue($probeSawDiagnostic);
    }

    public function testOpenWrapsTheOpenedResource(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'zef-stream-');
        self::assertIsString($file);
        file_put_contents($file, 'contents');

        try {
            $resource = StreamOpener::open($file, 'rb');
            self::assertIsResource($resource);
            $stream = new Stream($resource);

            self::assertSame('contents', $stream->getContents());
        } finally {
            unlink($file);
        }
    }

    private function memoryStream(string $mode = 'w+b'): Stream
    {
        return new Stream(fopen('php://memory', $mode));
    }

    private function stdoutStream(): Stream
    {
        return new Stream(fopen('php://stdout', 'wb'));
    }

    private function outputStream(): Stream
    {
        return new Stream(fopen('php://output', 'wb'));
    }

    private function failingStream(): Stream
    {
        return new Stream(fopen(self::FAILING_PROTOCOL . '://resource', 'r+b'));
    }

    private function noSeekStream(): Stream
    {
        return new Stream(fopen(self::NO_SEEK_PROTOCOL . '://resource', 'r+b'));
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
