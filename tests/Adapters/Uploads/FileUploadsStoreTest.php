<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Uploads;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Uploads\FileUploadsStore;
use Zef\Framework\Adapters\Uploads\UploadsStoreDirectory;
use Zef\Framework\Exception\InvariantViolationException;
use Zef\Framework\Exception\UnsupportedMediaTypeException;
use Zef\Framework\Exception\UploadTooLargeException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\UploadedFile;
use Zef\Test\Support\Http\SizelessStream;

/**
 * Pins the uploads lane's first writer: the fail-closed validation
 * order (the transport's own error code, then the size with its
 * unmeasurable refusal, then the type's exact match), the ceiling's
 * both-side boundary (the exact-at-ceiling upload lands, the one
 * byte past it refuses with its evidence carried), the landed
 * name's own grammar (thirty-two hexadecimal characters, no client
 * spelling anywhere in it), the PSR-7 move riding the interface's
 * own tmp+rename dance, and the construction-time bounds (the
 * ceiling of at least one byte, the non-empty allowlist of
 * non-empty strings, the directory posture).
 *
 * @internal
 */
#[CoversClass(FileUploadsStore::class)]
#[CoversClass(UploadsStoreDirectory::class)]
#[CoversClass(UploadTooLargeException::class)]
#[CoversClass(UnsupportedMediaTypeException::class)]
final class FileUploadsStoreTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-uploads-writer-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->directory);
    }

    public function testALandingAnswerableUploadLandsUnderTheWritersOwnName(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 64, ['application/pdf']);

        $path = $fileUploadsStore->store(
            new UploadedFile(
                Stream::fromString('uploaded payload'),
                16,
                \UPLOAD_ERR_OK,
                'report.pdf',
                'application/pdf',
            ),
        );

        self::assertSame(
            realpath($this->directory),
            \dirname($path),
            'the landed path answers the resolved lane address',
        );
        self::assertSame(1, preg_match('/^[a-f0-9]{32}$/D', basename($path)));
        self::assertFileExists($path);
        self::assertSame('uploaded payload', file_get_contents($path));
    }

    public function testAnUploadExactlyAtTheCeilingLands(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 4, ['image/png']);

        $path = $fileUploadsStore->store(
            new UploadedFile(Stream::fromString('abcd'), 4, \UPLOAD_ERR_OK, 'a.png', 'image/png'),
        );

        self::assertFileExists($path);
        self::assertSame('abcd', file_get_contents($path));
    }

    public function testAOneByteCeilingIsItsOwnAcceptableBound(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 1, ['image/png']);

        $path = $fileUploadsStore->store(
            new UploadedFile(Stream::fromString('x'), 1, \UPLOAD_ERR_OK, 'a.png', 'image/png'),
        );

        self::assertFileExists($path);
        self::assertSame('x', file_get_contents($path));
    }

    public function testAnUploadPastTheCeilingRefusesWithTheEvidenceCarried(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 4, ['image/png']);

        try {
            $fileUploadsStore->store(
                new UploadedFile(Stream::fromString('abcde'), 5, \UPLOAD_ERR_OK, 'a.png', 'image/png'),
            );
            self::fail('An upload past the ceiling must refuse.');
        } catch (UploadTooLargeException $uploadTooLargeException) {
            self::assertSame(413, $uploadTooLargeException->httpStatus());
            self::assertSame(5, $uploadTooLargeException->size);
            self::assertSame(4, $uploadTooLargeException->maxBytes);
            self::assertSame(
                "The uploaded file's 5 bytes exceed the 4-byte ceiling.",
                $uploadTooLargeException->getMessage(),
            );
        }
    }

    public function testAnUnmeasurableSizeRefusesAsTheHonestValidationError(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 4, ['image/png']);

        try {
            $fileUploadsStore->store(
                new UploadedFile(new SizelessStream(), null, \UPLOAD_ERR_OK, 'a.png', 'image/png'),
            );
            self::fail('An unmeasurable size must refuse.');
        } catch (ValidationException $validationException) {
            self::assertSame(
                'The uploaded file size cannot be measured, so it cannot'
                . ' be judged against the declared ceiling.',
                $validationException->getMessage(),
            );
        }
    }

    public function testAnUploadTheTransportAlreadyRefusedRefusesBeforeTheValidations(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 4, ['image/png']);

        try {
            $fileUploadsStore->store(
                new UploadedFile(Stream::fromString('ab'), 2, \UPLOAD_ERR_INI_SIZE, 'a.png', 'image/png'),
            );
            self::fail('An errored upload must refuse.');
        } catch (ValidationException $validationException) {
            self::assertSame(
                'Uploaded file is not available (error 1).',
                $validationException->getMessage(),
            );
        }
    }

    public function testATypeTheAllowlistDoesNotCarryRefusesWithBothSidesCarried(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 64, ['image/png', 'application/pdf']);

        try {
            $fileUploadsStore->store(
                new UploadedFile(Stream::fromString('x'), 1, \UPLOAD_ERR_OK, 'x.txt', 'text/plain'),
            );
            self::fail('A type the allowlist does not carry must refuse.');
        } catch (UnsupportedMediaTypeException $unsupportedMediaTypeException) {
            self::assertSame(415, $unsupportedMediaTypeException->httpStatus());
            self::assertSame('text/plain', $unsupportedMediaTypeException->clientMediaType);
            self::assertSame(
                ['image/png', 'application/pdf'],
                $unsupportedMediaTypeException->acceptedMediaTypes,
            );
            self::assertSame(
                "The uploaded file's media type 'text/plain' is not one the"
                . ' uploads lane accepts (accepted: image/png, application/pdf).',
                $unsupportedMediaTypeException->getMessage(),
            );
        }
    }

    public function testATypelessUploadRefusesNamingTheDeclaration(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 64, ['image/png']);

        try {
            $fileUploadsStore->store(
                new UploadedFile(Stream::fromString('x'), 1, \UPLOAD_ERR_OK, 'x.bin'),
            );
            self::fail('A typeless upload must refuse.');
        } catch (UnsupportedMediaTypeException $unsupportedMediaTypeException) {
            self::assertNull($unsupportedMediaTypeException->clientMediaType);
            self::assertSame(
                'The uploaded file declares no media type (accepted: image/png).',
                $unsupportedMediaTypeException->getMessage(),
            );
        }
    }

    public function testAMoveFailurePropagatesTheEngineBoundariesOwnRefusal(): void
    {
        $fileUploadsStore = new FileUploadsStore($this->directory, 64, ['image/png']);

        chmod($this->directory, 0o500);

        try {
            try {
                $fileUploadsStore->store(
                    new UploadedFile(Stream::fromString('ab'), 2, \UPLOAD_ERR_OK, 'a.png', 'image/png'),
                );
                self::fail("A failed move must propagate the machinery's refusal.");
            } catch (HttpException $httpException) {
                self::assertStringContainsString(
                    'Unable to create temporary upload target',
                    $httpException->getMessage(),
                );
            }
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testACeilingBelowOneByteRefusesTheConstruction(): void
    {
        try {
            new FileUploadsStore($this->directory, 0, ['image/png']);
            self::fail('A ceiling below one byte must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads store ceiling must be at least one byte.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEmptyAllowlistRefusesTheConstruction(): void
    {
        try {
            new FileUploadsStore($this->directory, 64, []);
            self::fail('An empty allowlist must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads store must accept at least one media type.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANonStringAllowlistEntryRefusesTheConstruction(): void
    {
        try {
            new FileUploadsStore($this->directory, 64, ['image/png', 42]);
            self::fail('A non-string allowlist entry must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads store media types must be non-empty strings, int given.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEmptyStringAllowlistEntryRefusesTheConstruction(): void
    {
        try {
            new FileUploadsStore($this->directory, 64, ['']);
            self::fail('An empty-string allowlist entry must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads store media types must be non-empty strings, string given.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARefusingDirectoryRefusesTheConstruction(): void
    {
        try {
            new FileUploadsStore($this->directory . '-missing', 64, ['image/png']);
            self::fail('A refusing directory must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store must be an existing writable non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheSizeRefusalCarriesItsEvidenceValidated(): void
    {
        try {
            new UploadTooLargeException(-1, 64);
            self::fail('A negative size must refuse the evidence.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'An upload-size refusal must carry a non-negative size.',
                $invariantViolationException->getMessage(),
            );
        }

        try {
            new UploadTooLargeException(1, 0);
            self::fail('A ceiling below one byte must refuse the evidence.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'An upload-size refusal must carry a ceiling of at least one byte.',
                $invariantViolationException->getMessage(),
            );
        }

        $zeroSizedRefusal = new UploadTooLargeException(0, 1);

        self::assertSame(0, $zeroSizedRefusal->size);
        self::assertSame(1, $zeroSizedRefusal->maxBytes);
        self::assertSame(413, $zeroSizedRefusal->httpStatus());
    }

    public function testTheTypeRefusalCarriesItsEvidenceValidated(): void
    {
        try {
            new UnsupportedMediaTypeException('text/plain', []);
            self::fail('An empty allowlist must refuse the evidence.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'An unsupported-media-type refusal must name at least one accepted media type.',
                $invariantViolationException->getMessage(),
            );
        }

        try {
            new UnsupportedMediaTypeException('text/plain', [42]);
            self::fail('A non-string entry must refuse the evidence.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'An accepted media type must be a non-empty string, int given.',
                $invariantViolationException->getMessage(),
            );
        }

        try {
            new UnsupportedMediaTypeException('text/plain', ['']);
            self::fail('An empty-string entry must refuse the evidence.');
        } catch (InvariantViolationException $invariantViolationException) {
            self::assertSame(
                'An accepted media type must be a non-empty string, string given.',
                $invariantViolationException->getMessage(),
            );
        }
    }

    /**
     * Removes a directory tree the test laid down, best-effort: a
     * tree the runner could not remove is the runner's own
     * environment, never a reason to redden the arm's own verdict.
     */
    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        foreach (\is_array($entries) ? $entries : [] as $base) {
            if ($base === '.' || $base === '..') {
                continue;
            }

            $entry = $directory . \DIRECTORY_SEPARATOR . $base;

            if (is_dir($entry)) {
                $this->removeTree($entry);
            } else {
                unlink($entry);
            }
        }

        rmdir($directory);
    }
}
