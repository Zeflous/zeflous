<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ScopedDiagnostics;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\UploadedFile;
use Zef\Framework\Http\UploadedMove;
use Zef\Framework\Http\UploadedPosition;
use Zef\Framework\Http\UploadedTarget;
use Zef\Framework\Http\UploadedTransfer;
use Zef\Test\Support\Http\CursoredStream;
use Zef\Test\Support\Http\LyingEofStreamWrapper;
use Zef\Test\Support\Http\NoSeekStreamWrapper;
use Zef\Test\Support\Http\ReplaySnapshotDoubleState;
use Zef\Test\Support\Http\ReplaySnapshotFunctionDoubles;
use Zef\Test\Support\Http\UnopenableStreamWrapper;
use Zef\Test\Support\Http\UnwritableStreamWrapper;

/**
 * @internal
 */
#[CoversClass(ScopedDiagnostics::class)]
#[CoversClass(UploadedFile::class)]
#[CoversClass(UploadedMove::class)]
#[CoversClass(UploadedPosition::class)]
#[CoversClass(UploadedTarget::class)]
#[CoversClass(UploadedTransfer::class)]
final class UploadedFileTest extends TestCase
{
    private const string LYING_EOF_PROTOCOL = 'zef-lying-eof';

    private const string NO_SEEK_PROTOCOL = 'zef-no-seek';

    private const string UNOPENABLE_PROTOCOL = 'zef-unopenable';

    private const string UNWRITABLE_PROTOCOL = 'zef-unwritable';

    #[Override]
    protected function setUp(): void
    {
        ReplaySnapshotFunctionDoubles::define();

        stream_wrapper_register(self::LYING_EOF_PROTOCOL, LyingEofStreamWrapper::class);
        stream_wrapper_register(self::NO_SEEK_PROTOCOL, NoSeekStreamWrapper::class);
        stream_wrapper_register(self::UNOPENABLE_PROTOCOL, UnopenableStreamWrapper::class);
        stream_wrapper_register(self::UNWRITABLE_PROTOCOL, UnwritableStreamWrapper::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        ReplaySnapshotFunctionDoubles::reset();

        stream_wrapper_unregister(self::LYING_EOF_PROTOCOL);
        stream_wrapper_unregister(self::NO_SEEK_PROTOCOL);
        stream_wrapper_unregister(self::UNOPENABLE_PROTOCOL);
        stream_wrapper_unregister(self::UNWRITABLE_PROTOCOL);
    }

    public function testConstructorRejectsANegativeSize(): void
    {
        $this->assertThrows('Uploaded file size must not be negative.', static function (): void {
            new UploadedFile(Stream::fromString('x'), -1);
        });
    }

    public function testConstructorAcceptsAZeroSize(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString(''), 0);

        self::assertSame(0, $uploadedFile->getSize());
    }

    public function testGetStreamAnswersThePayloadStream(): void
    {
        $stream = Stream::fromString('payload');

        $uploadedFile = new UploadedFile($stream);

        self::assertSame($stream, $uploadedFile->getStream());
    }

    #[DataProvider('provideUploadErrorCodes')]
    public function testGetStreamRejectsTheUploadErrorCodes(int $error): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'), error: $error);

        $this->assertThrows(
            "Uploaded file is not available (error {$error}).",
            static function () use ($uploadedFile): void {
                $uploadedFile->getStream();
            },
        );
    }

    public function testGetStreamRejectsAnAlreadyMovedUpload(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'));
        $uploadedFile->moveTo($this->targetPath('moved-get-stream'));

        $this->assertThrows('Uploaded file has already been moved.', static function () use ($uploadedFile): void {
            $uploadedFile->getStream();
        });
    }

    public function testMoveToWritesTheWholePayloadAndClosesTheStream(): void
    {
        $stream = Stream::fromString('uploaded payload');
        $target = $this->targetPath('whole-payload');
        $secondTarget = $this->targetPath('second-move');
        $uploadedFile = new UploadedFile($stream, 16, \UPLOAD_ERR_OK, 'report.pdf', 'application/pdf');

        $uploadedFile->moveTo($target);

        self::assertSame('uploaded payload', file_get_contents($target));
        // Scoped to THIS move's own staging sibling: a glob over the whole
        // shared temporary directory would observe every other process's
        // leftovers, and under mutation testing the sibling files earlier
        // mutants leave behind would turn this assertion into host noise.
        self::assertSame([], glob($target . '.zef-tmp-*'));
        $this->assertThrows('Stream detached.', static function () use ($stream): void {
            $stream->tell();
        });
        $this->assertThrows(
            'Uploaded file has already been moved.',
            static function () use ($uploadedFile, $secondTarget): void {
                $uploadedFile->moveTo($secondTarget);
            },
        );
    }

    public function testMoveToEndsTheCopyWhenAChunkReadsEmptyBelowEof(): void
    {
        $uploadedFile = new UploadedFile($this->lyingEofStream());
        $target = $this->targetPath('lying-eof');

        $uploadedFile->moveTo($target);

        self::assertSame('AB', file_get_contents($target));
    }

    public function testMoveToRejectsAnEmptyTargetPath(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'));

        $this->assertThrows('Target path must not be empty.', static function () use ($uploadedFile): void {
            $uploadedFile->moveTo('');
        });
    }

    #[DataProvider('provideUploadErrorCodes')]
    public function testMoveToRejectsTheUploadErrorCodes(int $error): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'), error: $error);
        $target = $this->targetPath('error-code');

        $this->assertThrows(
            "Cannot move uploaded file with error code {$error}.",
            static function () use ($uploadedFile, $target): void {
                $uploadedFile->moveTo($target);
            },
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideUploadErrorCodes(): iterable
    {
        yield 'INI_SIZE' => [\UPLOAD_ERR_INI_SIZE];

        yield 'FORM_SIZE' => [\UPLOAD_ERR_FORM_SIZE];

        yield 'PARTIAL' => [\UPLOAD_ERR_PARTIAL];

        yield 'NO_FILE' => [\UPLOAD_ERR_NO_FILE];

        yield 'NO_TMP_DIR' => [\UPLOAD_ERR_NO_TMP_DIR];

        yield 'CANNOT_WRITE' => [\UPLOAD_ERR_CANT_WRITE];

        yield 'EXTENSION' => [\UPLOAD_ERR_EXTENSION];
    }

    public function testMoveToRejectsAnAlreadyMovedUpload(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'));
        $uploadedFile->moveTo($this->targetPath('moved-second-move'));

        $again = $this->targetPath('moved-second-move-again');

        $this->assertThrows(
            'Uploaded file has already been moved.',
            static function () use ($uploadedFile, $again): void {
                $uploadedFile->moveTo($again);
            },
        );
    }

    public function testMoveToRejectsAMissingDestinationDirectory(): void
    {
        $missing = sys_get_temp_dir() . '/zef-upload-missing-' . bin2hex(random_bytes(4)) . '/payload.dat';
        $directory = \dirname($missing);
        $uploadedFile = new UploadedFile(Stream::fromString('payload'));

        $this->assertThrows(
            "Destination directory does not exist: '{$directory}'.",
            static function () use ($uploadedFile, $missing): void {
                $uploadedFile->moveTo($missing);
            },
        );
    }

    public function testMoveToRejectsATargetThatCannotBeCreated(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'));
        $target = self::UNOPENABLE_PROTOCOL . '://dir/target.txt';

        try {
            $uploadedFile->moveTo($target);
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertStringStartsWith('Unable to create temporary upload target: ', $httpException->getMessage());
            self::assertStringContainsString($target, $httpException->getMessage());
        }
    }

    public function testMoveToRejectsATargetTheEngineCannotWriteAndRestoresTheReadPosition(): void
    {
        $stream = Stream::fromString('payload');
        $stream->seek(2);

        $uploadedFile = new UploadedFile($stream);
        $target = self::UNWRITABLE_PROTOCOL . '://dir/target.txt';

        $this->assertThrows('Unable to write uploaded file.', static function () use ($uploadedFile, $target): void {
            $uploadedFile->moveTo($target);
        });

        self::assertSame(2, $stream->tell());
        self::assertSame('yload', $stream->getContents());
        $stream->rewind();
        self::assertSame('payload', (string) $stream);
    }

    public function testMoveToSkipsTheReadPositionRestoreForANonSeekablePayload(): void
    {
        $stream = new Stream(popen('echo zef-pipe-payload', 'rb'));
        $uploadedFile = new UploadedFile($stream);
        $target = self::UNWRITABLE_PROTOCOL . '://dir/target.txt';

        self::assertFalse($stream->isSeekable());

        $this->assertThrows('Unable to write uploaded file.', static function () use ($uploadedFile, $target): void {
            $uploadedFile->moveTo($target);
        });
    }

    public function testMoveToRejectsAPayloadWhoseEngineRewindFails(): void
    {
        $uploadedFile = new UploadedFile($this->noSeekStream());
        $target = $this->targetPath('no-seek-rewind');

        // The engine emits its fseek() diagnostic for the disagreeing
        // wrapper; the scoped handler in the test keeps the failure on the
        // exception path, mirroring the established seek-disagreement test.
        set_error_handler(static fn (): bool => true);

        try {
            $uploadedFile->moveTo($target);
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame('Stream is not seekable.', $httpException->getMessage());
        } finally {
            restore_error_handler();
        }

        self::assertFileDoesNotExist($target);
    }

    public function testMoveToCleansUpTheTemporaryFileWhenTheRenameFails(): void
    {
        $base = sys_get_temp_dir() . '/zef-upload-rename-' . bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $target = $base . '/final';
        mkdir($target, 0o777, true);

        try {
            $uploadedFile = new UploadedFile(Stream::fromString('payload'));

            try {
                $uploadedFile->moveTo($target);
                self::fail('HttpException expected.');
            } catch (HttpException $httpException) {
                self::assertStringStartsWith(
                    "Unable to finalize uploaded file to '{$target}': ",
                    $httpException->getMessage(),
                );
            }

            self::assertSame([], glob($base . '/*.zef-tmp-*'));
        } finally {
            rmdir($target);
            rmdir($base);
        }
    }

    public function testTheFailedMoveStagesItsPayloadUnderTheTemporarySiblingNameItself(): void
    {
        // The temporary sibling's name is the move's own contract -- the
        // staging spelling that makes the rename atomic -- but its shape
        // was observed only through post-hoc glob-emptiness, which passes
        // for any spelling the cleanup happens to remove. The refusal arm
        // makes the observation assertion-owned: the cleanup's unlink
        // refuses once, the staging file survives, and the survivor's name
        // is pinned exactly (the target, the '.zef-tmp-' marker, and the
        // 16 hex characters of the 8 entropy bytes).
        $base = sys_get_temp_dir() . '/zef-upload-staging-' . bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $target = $base . '/final';
        mkdir($target, 0o777, true);

        ReplaySnapshotDoubleState::$unlinkAnswers = [false];

        try {
            $uploadedFile = new UploadedFile(Stream::fromString('staged payload'));

            try {
                $uploadedFile->moveTo($target);
                self::fail('HttpException expected.');
            } catch (HttpException $httpException) {
                self::assertStringStartsWith(
                    "Unable to finalize uploaded file to '{$target}': ",
                    $httpException->getMessage(),
                );
            }

            $survivors = glob($base . '/*.zef-tmp-*');
            self::assertIsArray($survivors);
            self::assertCount(1, $survivors, 'exactly one staging sibling survives the refused cleanup');

            self::assertMatchesRegularExpression(
                '~^' . preg_quote($target, '~') . '\.zef-tmp-[0-9a-f]{16}$~',
                $survivors[0],
                'the staging sibling is the target plus the marker and the 8 entropy bytes',
            );
            self::assertSame('staged payload', file_get_contents($survivors[0]));
            unlink($survivors[0]);
        } finally {
            rmdir($target);
            rmdir($base);
        }
    }

    public function testTheFailedMoveRestoresTheStreamsOriginalPosition(): void
    {
        // The capture-before-copy is what lets a failed move be retried
        // over the same bytes -- but no assertion owned the restored
        // position, so the capture's removal was only observable through
        // the engine's own EOF state. The probe stream starts mid-payload,
        // the move fails on the rename, and the position must come back to
        // where it started.
        $base = sys_get_temp_dir() . '/zef-upload-position-' . bin2hex(random_bytes(4));
        mkdir($base, 0o777, true);
        $target = $base . '/final';
        mkdir($target, 0o777, true);

        try {
            $stream = Stream::fromString('0123456789');
            $stream->seek(4);
            $uploadedFile = new UploadedFile($stream);

            try {
                $uploadedFile->moveTo($target);
                self::fail('HttpException expected.');
            } catch (HttpException) {
                // The rename failure is the other test's question; here it
                // is only the trigger for the restore arm under test.
            }

            self::assertSame(4, $stream->tell(), 'the failed move restores the pre-copy position');
        } finally {
            rmdir($target);
            rmdir($base);
        }
    }

    public function testTheSuccessfulMoveNeverSeeksTheStreamBack(): void
    {
        // The success flag's false arm is the whole difference between
        // "the payload crossed" and "put the stream back where it
        // started": a mutant that runs the failure cleanup after a
        // successful rename leaves no file to observe (the rename already
        // moved the staging sibling), so the restore's seek is the only
        // witness -- and the recording stream answers it. A successful
        // move must answer exactly zero seeks.
        $target = $this->targetPath('no-restore');

        $cursoredStream = new CursoredStream(5, 0);
        $uploadedFile = new UploadedFile($cursoredStream);

        try {
            $uploadedFile->moveTo($target);

            self::assertFileExists($target);
            self::assertSame(0, $cursoredStream->seekCalls, 'a successful move never seeks the stream back');
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testTheCopyResumesPastAShortWrite(): void
    {
        // The engine may accept fewer bytes than the copy hands it, and
        // the copy's resume arithmetic (advance by what the engine
        // accepted, slice the remainder) is the whole difference between
        // the payload and a truncated file. The fwrite double's byte
        // budget forces a four-byte short write per call; the target must
        // still carry the exact payload. The source stream is built BEFORE
        // the budget is armed: the double shadows the whole Http
        // namespace's fwrite calls, and Stream::fromString itself writes
        // the payload through it.
        $target = $this->targetPath('short-write');
        $uploadedFile = new UploadedFile(Stream::fromString('0123456789'));

        ReplaySnapshotDoubleState::$fwriteChunk = 4;

        try {
            $uploadedFile->moveTo($target);

            self::assertSame('0123456789', file_get_contents($target));
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testTheCopyRefusesAWriteThatAcceptsNothing(): void
    {
        // A write that accepts zero bytes is data loss in progress: the
        // copy must fail closed with the documented message rather than
        // spin on a stream that never advances. The source stream is
        // built BEFORE the answer is armed (Stream::fromString writes
        // through the same double), so the armed zero lands on the copy's
        // own first write.
        $target = $this->targetPath('zero-write');
        $uploadedFile = new UploadedFile(Stream::fromString('payload'));

        ReplaySnapshotDoubleState::$fwriteAnswers = [0];

        try {
            $this->assertThrows(
                'Unable to write uploaded file.',
                static function () use ($uploadedFile, $target): void {
                    $uploadedFile->moveTo($target);
                },
            );
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function testGetSizePrefersTheExplicitSize(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'), 12);

        self::assertSame(12, $uploadedFile->getSize());
    }

    public function testGetSizeFallsBackToTheStreamSize(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'));

        self::assertSame(7, $uploadedFile->getSize());
    }

    public function testGetErrorAnswersTheUploadErrorCode(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString('payload'), error: \UPLOAD_ERR_PARTIAL);

        self::assertSame(\UPLOAD_ERR_PARTIAL, $uploadedFile->getError());
    }

    public function testTheClientMetadataRoundTrips(): void
    {
        $upload = new UploadedFile(
            Stream::fromString('payload'),
            7,
            \UPLOAD_ERR_PARTIAL,
            'report.pdf',
            'application/pdf',
        );

        self::assertSame('report.pdf', $upload->getClientFilename());
        self::assertSame('application/pdf', $upload->getClientMediaType());

        $default = new UploadedFile(Stream::fromString('payload'));

        self::assertNull($default->getClientFilename());
        self::assertNull($default->getClientMediaType());
        self::assertSame(\UPLOAD_ERR_OK, $default->getError());
    }

    private function targetPath(string $key): string
    {
        return sys_get_temp_dir() . '/zef-upload-' . $key . '-' . bin2hex(random_bytes(4)) . '.dat';
    }

    private function lyingEofStream(): Stream
    {
        return new Stream(fopen(self::LYING_EOF_PROTOCOL . '://payload', 'rb'));
    }

    private function noSeekStream(): Stream
    {
        return new Stream(fopen(self::NO_SEEK_PROTOCOL . '://payload', 'rb'));
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
