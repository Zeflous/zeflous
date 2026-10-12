<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Psr17Factory;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamMode;
use Zef\Framework\Http\StreamOpener;
use Zef\Framework\Http\UploadedFile;
use Zef\Test\Support\Http\UriObjectDouble;

/**
 * @internal
 */
#[CoversClass(Psr17Factory::class)]
#[CoversClass(StreamMode::class)]
final class Psr17FactoryTest extends TestCase
{
    private Psr17Factory $psr17Factory;

    #[Override]
    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
    }

    public function testCreateRequestBuildsARequestFromAUriString(): void
    {
        $request = $this->psr17Factory->createRequest('GET', 'https://example.com:8443/path?q=1');

        self::assertSame('GET', $request->getMethod());
        self::assertSame('https', $request->getUri()->getScheme());
        self::assertSame('example.com', $request->getUri()->getHost());
        self::assertSame(8443, $request->getUri()->getPort());
        self::assertSame('/path?q=1', $request->getRequestTarget());
    }

    public function testCreateRequestAcceptsAUriValueObject(): void
    {
        $uriObjectDouble = new UriObjectDouble('https://example.com/report');

        $request = $this->psr17Factory->createRequest('POST', $uriObjectDouble);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/report', $request->getRequestTarget());
    }

    public function testCreateRequestRejectsAnythingThatIsNotAStringOrUri(): void
    {
        $this->assertThrows(
            'Request URI must be a string or a UriInterface instance.',
            static function (): void {
                new Psr17Factory()->createRequest('GET', 42);
            },
        );
    }

    public function testCreateResponseAnswersTheStatusAndTheResolvedPhrase(): void
    {
        $response = $this->psr17Factory->createResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());

        $custom = $this->psr17Factory->createResponse(418, 'Teapot Tuesday');

        self::assertSame(418, $custom->getStatusCode());
        self::assertSame('Teapot Tuesday', $custom->getReasonPhrase());
    }

    public function testCreateResponseRejectsAnInvalidStatus(): void
    {
        $this->assertThrows('Invalid HTTP status code: 99.', static function (): void {
            new Psr17Factory()->createResponse(99);
        });
    }

    public function testCreateServerRequestCarriesTheServerParamsAndTheUri(): void
    {
        $request = $this->psr17Factory->createServerRequest(
            'POST',
            'https://example.com/submit',
            ['REQUEST_METHOD' => 'POST', 'HTTPS' => 'on'],
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/submit', $request->getRequestTarget());
        self::assertSame(['REQUEST_METHOD' => 'POST', 'HTTPS' => 'on'], $request->getServerParams());
    }

    public function testCreateServerRequestAcceptsAUriValueObject(): void
    {
        $uriObjectDouble = new UriObjectDouble('https://example.com/api');

        $request = $this->psr17Factory->createServerRequest('PUT', $uriObjectDouble, ['S' => '1']);

        self::assertSame('PUT', $request->getMethod());
        self::assertSame('example.com', $request->getUri()->getHost());
    }

    public function testCreateServerRequestRejectsAnythingThatIsNotAStringOrUri(): void
    {
        $this->assertThrows(
            'Request URI must be a string or a UriInterface instance.',
            static function (): void {
                new Psr17Factory()->createServerRequest('GET', null);
            },
        );
    }

    public function testCreateStreamAnswersAWritableSeekableStreamOverTheContent(): void
    {
        $stream = $this->psr17Factory->createStream('payload');

        self::assertSame('payload', (string) $stream);
        self::assertTrue($stream->isSeekable());
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());

        self::assertSame('', (string) $this->psr17Factory->createStream());
    }

    public function testCreateStreamFromFileAnswersTheFilesContent(): void
    {
        $file = $this->tempFile('factory payload');

        try {
            $stream = $this->psr17Factory->createStreamFromFile($file);

            self::assertSame('factory payload', $stream->getContents());
        } finally {
            @unlink($file);
        }
    }

    public function testCreateStreamFromFileOpensWriteModes(): void
    {
        $file = $this->tempFile('x');

        try {
            $stream = $this->psr17Factory->createStreamFromFile($file, 'w+b');

            self::assertSame(4, $stream->write('next'));
            self::assertTrue($stream->isWritable());
        } finally {
            @unlink($file);
        }
    }

    public function testCreateStreamFromFileRejectsAnEmptyFilename(): void
    {
        $this->assertThrows('Filename must not be empty.', static function (): void {
            new Psr17Factory()->createStreamFromFile('');
        });
    }

    #[DataProvider('provideCreateStreamFromFileRejectsInvalidModesCases')]
    public function testCreateStreamFromFileRejectsInvalidModes(string $mode): void
    {
        $this->assertThrows("Invalid stream mode '{$mode}'.", static function () use ($mode): void {
            new Psr17Factory()->createStreamFromFile('/dev/null', $mode);
        });
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCreateStreamFromFileRejectsInvalidModesCases(): iterable
    {
        yield 'empty' => [''];

        yield 'wrong letter' => ['z'];

        yield 'flag before letter' => ['br'];

        yield 'trailing bytes' => ['rbx'];

        yield 'double flag' => ['rbb'];

        yield 'double letter' => ['rr'];

        yield 'double update' => ['r++'];
    }

    #[DataProvider('provideCreateStreamFromFileAcceptsTheModeGrammarCases')]
    public function testCreateStreamFromFileAcceptsTheModeGrammar(string $mode): void
    {
        $file = $this->tempFile('mode check');

        try {
            $stream = $this->psr17Factory->createStreamFromFile($file, $mode);

            self::assertInstanceOf(Stream::class, $stream);
        } finally {
            @unlink($file);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCreateStreamFromFileAcceptsTheModeGrammarCases(): iterable
    {
        yield 'read' => ['r'];

        yield 'read binary' => ['rb'];

        yield 'read update' => ['r+'];

        yield 'read update binary' => ['r+b'];

        yield 'read text update' => ['r+t'];

        yield 'write update binary' => ['w+b'];

        yield 'append' => ['a'];

        yield 'control update binary' => ['c+b'];

        yield 'read with e directive' => ['r+e'];

        yield 'update then flag then e' => ['r+be'];

        yield 'flag then e' => ['rbe'];
    }

    public function testCreateStreamFromFileAcceptsTheExclusiveModeOnAFreshTarget(): void
    {
        $file = $this->tempFile('mode check');
        @unlink($file);

        try {
            $stream = $this->psr17Factory->createStreamFromFile($file, 'x+b');

            self::assertInstanceOf(Stream::class, $stream);
            self::assertTrue($stream->isWritable());
        } finally {
            @unlink($file);
        }
    }

    public function testCreateStreamFromFileFailsClosedOnAMissingFile(): void
    {
        $missing = '/zef-does-not-exist-' . bin2hex(random_bytes(4)) . '/payload.txt';

        try {
            $this->psr17Factory->createStreamFromFile($missing, 'rb');
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertStringStartsWith('Unable to open stream ' . $missing . ': ', $httpException->getMessage());
        }
    }

    public function testCreateStreamFromResourceAnswersAStreamOverTheResource(): void
    {
        $resource = StreamOpener::open('php://temp', 'r+b');
        fwrite($resource, 'resource payload');
        rewind($resource);

        try {
            $stream = $this->psr17Factory->createStreamFromResource($resource);

            self::assertSame('resource payload', $stream->getContents());
        } finally {
            fclose($resource);
        }
    }

    public function testCreateStreamFromResourceRejectsAnythingThatIsNotAResource(): void
    {
        $this->assertThrows('Resource required.', static function (): void {
            new Psr17Factory()->createStreamFromResource('not-a-resource');
        });
    }

    public function testCreateStreamFromResourceRejectsAWriteOnlyResource(): void
    {
        $file = $this->tempFile('write only');
        $resource = StreamOpener::open($file, 'wb');

        try {
            $this->assertThrows('Resource must be readable.', static function () use ($resource): void {
                new Psr17Factory()->createStreamFromResource($resource);
            });
        } finally {
            fclose($resource);
            @unlink($file);
        }
    }

    public function testCreateUploadedFileDerivesTheSizeFromTheStream(): void
    {
        $upload = $this->psr17Factory->createUploadedFile(
            Stream::fromString('payload'),
            null,
            \UPLOAD_ERR_OK,
            'report.pdf',
            'application/pdf',
        );

        self::assertSame(7, $upload->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $upload->getError());
        self::assertSame('report.pdf', $upload->getClientFilename());
        self::assertSame('application/pdf', $upload->getClientMediaType());
        self::assertSame('payload', (string) $upload->getStream());
    }

    public function testCreateUploadedFilePrefersTheExplicitSize(): void
    {
        $upload = $this->psr17Factory->createUploadedFile(Stream::fromString('payload'), 12);

        self::assertSame(12, $upload->getSize());
        self::assertNull($this->psr17Factory->createUploadedFile(Stream::fromString(''))->getClientFilename());
    }

    public function testCreateUploadedFileRejectsAnUnreadableStream(): void
    {
        $file = $this->tempFile('write only');
        $resource = StreamOpener::open($file, 'wb');

        try {
            $this->assertThrows('Uploaded file stream must be readable.', static function () use ($resource): void {
                new Psr17Factory()->createUploadedFile(new Stream($resource));
            });
        } finally {
            fclose($resource);
            @unlink($file);
        }
    }

    public function testCreateUploadedFileRejectsANegativeSize(): void
    {
        $this->assertThrows('Uploaded file size must not be negative.', static function (): void {
            new Psr17Factory()->createUploadedFile(Stream::fromString('payload'), -1);
        });
    }

    public function testCreateUriAnswersTheUriValueObject(): void
    {
        $uri = $this->psr17Factory->createUri('https://example.com:8080/base?q=v#frag');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/base', $uri->getPath());
        self::assertSame('q=v', $uri->getQuery());
        self::assertSame('frag', $uri->getFragment());

        self::assertSame('', $this->psr17Factory->createUri()->getPath());
    }

    public function testTheDefaultUploadedFileCanBeMoved(): void
    {
        $target = $this->tempFile('');
        @unlink($target);

        $upload = $this->psr17Factory->createUploadedFile(Stream::fromString('moved payload'));
        self::assertInstanceOf(UploadedFile::class, $upload);

        try {
            $upload->moveTo($target);

            self::assertSame('moved payload', file_get_contents($target));
        } finally {
            @unlink($target);
        }
    }

    /**
     * @return non-empty-string
     */
    private function tempFile(string $content): string
    {
        $file = sys_get_temp_dir() . '/zef-psr17-' . bin2hex(random_bytes(4)) . '.dat';
        file_put_contents($file, $content);

        return $file;
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
