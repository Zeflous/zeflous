<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Zef\Framework\Http\ErrorReplayStore;
use Zef\Framework\Http\ReplayBodyCapture;
use Zef\Framework\Http\ReplayBodyDecoder;
use Zef\Framework\Http\ReplayBodyReader;
use Zef\Framework\Http\ReplayPayloadSanitizer;
use Zef\Framework\Http\ReplaySnapshotException;
use Zef\Framework\Http\ReplaySnapshotWriter;
use Zef\Framework\Http\ReplayStorageDirectory;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamSnapshot;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\RawFactsServerRequest;
use Zef\Test\Support\Http\RawPathUri;

/** @internal */
#[CoversClass(ErrorReplayStore::class)]
#[CoversClass(ReplayPayloadSanitizer::class)]
#[CoversClass(ReplayBodyCapture::class)]
#[CoversClass(ReplayBodyDecoder::class)]
#[CoversClass(ReplayBodyReader::class)]
#[CoversClass(StreamSnapshot::class)]
#[CoversClass(ReplaySnapshotWriter::class)]
#[CoversClass(ReplayStorageDirectory::class)]
final class ErrorReplayStoreTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-replay-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');
        foreach (\is_array($files) ? $files : [] as $file) {
            if (!is_file($file) && !is_link($file)) {
                continue;
            }

            unlink($file);
        }

        if (!is_dir($this->directory)) {
            return;
        }

        rmdir($this->directory);
    }

    public function testStoresOnlySanitizedRequestDataWithPrivateFilePermissions(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite(
            $resource,
            '{"username":"alice","password":"hunter2","nested":{"access_token":"secret123"}'
                . ',"count":3,"enabled":true,"empty":null}',
        );
        rewind($resource);
        $stream = new Stream($resource);
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/users?token=secret123&filter=active'),
            [],
            [],
            [],
            [],
            null,
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer secret123',
                'X-Request-ID' => 'req-replay-1',
            ],
            $stream,
        );

        $id = new ErrorReplayStore($this->directory)->save(
            $serverRequest,
            new RuntimeException('request failed'),
            400,
            'req-replay-1',
        );

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $id);
        self::assertSame(0, $stream->tell(), 'snapshot capture restores the original body cursor');
        $file = $this->directory . '/' . $id . '.json';
        self::assertFileExists($file);
        self::assertSame(0o600, fileperms($file) & 0o777);

        $contents = file_get_contents($file);
        self::assertIsString($contents);
        self::assertStringNotContainsString('secret123', $contents);
        self::assertStringNotContainsString('hunter2', $contents);
        self::assertStringNotContainsString('Authorization', $contents);

        $snapshot = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);
        self::assertSame('/users', $snapshot['path']);
        $query = $snapshot['query'];
        self::assertIsString($query);
        self::assertStringContainsString('filter=active', $query);
        self::assertStringContainsString('token=%5BREDACTED%5D', $query);
        self::assertTrue($snapshot['body_replayable']);
        $bodyField = $snapshot['body'];
        self::assertIsString($bodyField);
        $body = json_decode($bodyField, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('[REDACTED]', $body['password']);
        $nested = $body['nested'] ?? null;
        self::assertIsArray($nested);
        self::assertSame('[REDACTED]', $nested['access_token']);
        self::assertSame(3, $body['count']);
        self::assertTrue($body['enabled']);
        self::assertNull($body['empty']);
    }

    public function testOmitsFreeFormTextBodiesBecauseRedactionCannotBeGuaranteed(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, 'password=hunter2&access_token=secret123');
        rewind($resource);
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/upload'),
            [],
            [],
            [],
            [],
            null,
            ['Content-Type' => 'text/plain'],
            new Stream($resource),
        );

        $id = new ErrorReplayStore($this->directory)->save(
            $serverRequest,
            new RuntimeException('request failed'),
            500,
            'req-replay-2',
        );
        $snapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($snapshot);

        self::assertFalse($snapshot['body_replayable']);
        self::assertSame('', $snapshot['body']);
        self::assertStringNotContainsString(
            'hunter2',
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
        );
        self::assertStringNotContainsString(
            'secret123',
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
        );
    }

    public function testOmitsXmlBodiesWithoutAFormatAwareParser(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, '<user><password>hunter2</password><name>alice</name></user>');
        rewind($resource);
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/xml'),
            [],
            [],
            [],
            [],
            null,
            ['Content-Type' => 'application/xml'],
            new Stream($resource),
        );

        $id = new ErrorReplayStore($this->directory)->save(
            $serverRequest,
            new RuntimeException('request failed'),
            400,
            'req-replay-xml',
        );
        $snapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($snapshot);

        self::assertFalse($snapshot['body_replayable']);
        self::assertSame('', $snapshot['body']);
        self::assertStringNotContainsString(
            'hunter2',
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
        );
        self::assertStringNotContainsString(
            'alice',
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
        );
    }

    public function testRejectsDeceptiveJsonContentTypeSubstrings(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, '{"token":"secret123"}');
        rewind($resource);
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/submit'),
            [],
            [],
            [],
            [],
            null,
            ['Content-Type' => 'application/json-evil'],
            new Stream($resource),
        );

        $id = new ErrorReplayStore($this->directory)->save(
            $serverRequest,
            new RuntimeException('request failed'),
            500,
            'req-replay-invalid-media',
        );
        $snapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($snapshot);

        self::assertFalse($snapshot['body_replayable']);
        self::assertSame('', $snapshot['body']);
        self::assertStringNotContainsString(
            'secret123',
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
        );
    }

    public function testEmptyAndOversizedBodiesHaveExplicitReplayability(): void
    {
        $errorReplayStore = new ErrorReplayStore($this->directory);
        $emptyRequest = new ServerRequest(
            'GET',
            new Uri('https://app.example.test/users'),
        );
        $emptyId = $errorReplayStore->save($emptyRequest, new RuntimeException('empty'), 404, 'empty-req');
        $emptySnapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $emptyId . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($emptySnapshot);
        self::assertTrue($emptySnapshot['body_replayable']);
        self::assertSame('', $emptySnapshot['body']);

        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, '{"payload":"' . str_repeat('x', 70_000) . '"}');
        rewind($resource);
        $largeRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/users'),
            [],
            [],
            [],
            [],
            null,
            ['Content-Type' => 'application/json'],
            new Stream($resource),
        );
        $largeId = $errorReplayStore->save($largeRequest, new RuntimeException('large'), 413, 'large-req');
        $largeSnapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $largeId . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($largeSnapshot);
        self::assertFalse($largeSnapshot['body_replayable']);
        self::assertSame('', $largeSnapshot['body']);
    }

    public function testRefusesReplayDirectoriesWithGroupOrWorldPermissions(): void
    {
        self::assertTrue(chmod($this->directory, 0o755));

        try {
            $this->expectException(InvalidArgumentException::class);
            new ErrorReplayStore($this->directory);
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testFailsClosedWhenDocumentRootCannotBeResolved(): void
    {
        $hadRoot = \array_key_exists('DOCUMENT_ROOT', $_SERVER);
        $previousRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $this->directory . '/missing-public-root';

        try {
            $this->expectException(InvalidArgumentException::class);
            new ErrorReplayStore($this->directory);
        } finally {
            if ($hadRoot) {
                $_SERVER['DOCUMENT_ROOT'] = $previousRoot;
            } else {
                unset($_SERVER['DOCUMENT_ROOT']);
            }
        }
    }

    public function testRefusesReplayStorageInsideDocumentRoot(): void
    {
        $previous = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $publicRoot = $this->directory . '/public';
        $replayDirectory = $publicRoot . '/replays';
        mkdir($publicRoot, 0o700);
        mkdir($replayDirectory, 0o700);
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        try {
            $this->expectException(InvalidArgumentException::class);
            new ErrorReplayStore($replayDirectory);
        } finally {
            if ($previous === null) {
                unset($_SERVER['DOCUMENT_ROOT']);
            } else {
                $_SERVER['DOCUMENT_ROOT'] = $previous;
            }

            rmdir($replayDirectory);
            rmdir($publicRoot);
        }
    }

    public function testAnAlreadyPresentSnapshotFileIsRefused(): void
    {
        $id = str_repeat('b', 32);

        ReplaySnapshotWriter::write($this->directory, $id, '{"one":true}');

        set_error_handler(static fn (): bool => true);

        try {
            ReplaySnapshotWriter::write($this->directory, $id, '{"two":true}');
            self::fail('An existing snapshot file must be refused.');
        } catch (ReplaySnapshotException $replaySnapshotException) {
            self::assertSame('Unable to create an exclusive replay snapshot.', $replaySnapshotException->getMessage());
        } finally {
            restore_error_handler();
        }
    }

    public function testFormUrlEncodedBodiesAreRedactedAndReplayable(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, 'username=alice&password=hunter2');
        rewind($resource);
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/users'),
            [],
            [],
            [],
            [],
            null,
            ['Content-Type' => 'application/x-www-form-urlencoded', 'X-Request-ID' => 'form-req'],
            new Stream($resource),
        );

        $id = new ErrorReplayStore($this->directory)->save(
            $serverRequest,
            new RuntimeException('form request failed'),
            400,
            'form-req',
        );

        $contents = file_get_contents($this->directory . '/' . $id . '.json');
        self::assertIsString($contents);
        self::assertStringNotContainsString('hunter2', $contents);

        $snapshot = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);
        self::assertTrue($snapshot['body_replayable']);

        $body = $snapshot['body'];
        self::assertIsString($body);
        self::assertStringContainsString('username=alice', $body);
        self::assertStringNotContainsString('hunter2', $body);
    }

    public function testTheSnapshotNormalisesHostileRequestLineFacts(): void
    {
        $relativePathRequest = new ServerRequest('GET', new Uri('users'));

        $relativeSnapshot = $this->savedSnapshot($relativePathRequest);

        self::assertSame('/', $relativeSnapshot['path'], 'a path without a leading slash is refused');

        $rawFactsRequest = new RawFactsServerRequest(
            new ServerRequest('GET', new Uri('https://app.example.test/users')),
            'pätch',
            new RawPathUri("/us\x01ers"),
            [],
        );

        $rawSnapshot = $this->savedSnapshot($rawFactsRequest);

        self::assertSame('/', $rawSnapshot['path'], 'a control byte in the path is refused');
        self::assertSame('PÄTCH', $rawSnapshot['method'], 'a multibyte method token is uppercased whole');

        $invalidUtf8PathRequest = new RawFactsServerRequest(
            new ServerRequest('GET', new Uri('https://app.example.test/users')),
            'GET',
            new RawPathUri("/u\xB1s"),
            [],
        );

        self::assertSame(
            "/u\u{FFFD}s",
            $this->savedSnapshot($invalidUtf8PathRequest)['path'],
            'an invalid UTF-8 path byte survives as the substitution marker',
        );
    }

    public function testTheSnapshotCarriesEverySanitisedHeaderFact(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://app.example.test/users'),
            [],
            [],
            [],
            [],
            null,
            [
                'X-Api-Key' => 'k1',
                'Accept' => 'text/html',
                'Content-Length' => '42',
                'X-Keep' => 'yes',
                'X-Trace' => 'password=secret123',
                'X-Long' => str_repeat('é', 600),
                'X-Multi' => ['v0', 'v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8', 'v9', 'v10', 'v11'],
                'X-Bin' => "bad \xB1 byte",
            ],
        );

        $contents = $this->savedContents($serverRequest);
        $snapshot = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);

        self::assertArrayHasKey('timestamp', $snapshot);
        $timestamp = $snapshot['timestamp'];
        self::assertIsString($timestamp);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $timestamp);
        self::assertSame(
            [
                'Accept' => ['text/html'],
                'X-Keep' => ['yes'],
                'X-Trace' => ['password=[REDACTED]'],
                'X-Long' => [str_repeat('é', 512)],
                'X-Multi' => ['v0', 'v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8', 'v9'],
                'X-Bin' => ['bad ? byte'],
            ],
            $snapshot['headers'],
        );
    }

    public function testTheQueryGuardBoundsLengthInCharactersAndRefusesTheOversized(): void
    {
        $exactBound = new ServerRequest(
            'GET',
            new Uri('https://app.example.test/users?a=1&q=' . str_repeat('q', 8186)),
        );

        self::assertSame('a=1&q=' . str_repeat('q', 4096), $this->savedSnapshot($exactBound)['query']);

        // The real URI percent-encodes multibyte bytes out of the query, so
        // the character-scale length bound is only reachable through a
        // double that answers the raw, still-encoded form.
        $multibyte = new RawFactsServerRequest(
            new ServerRequest('GET', new Uri('https://app.example.test/users')),
            'GET',
            new RawPathUri('/users', 'f=' . str_repeat('é', 8098)),
            [],
        );

        self::assertSame(
            'f=' . urlencode(str_repeat('é', 4096)),
            $this->savedSnapshot($multibyte)['query'],
        );

        $oversized = new ServerRequest(
            'GET',
            new Uri('https://app.example.test/users?' . str_repeat('a', 9000)),
        );

        self::assertSame('', $this->savedSnapshot($oversized)['query']);
    }

    /**
     * Saves one snapshot and answers its decoded form.
     *
     * @return array<array-key, mixed> the decoded snapshot
     */
    private function savedSnapshot(ServerRequestInterface $serverRequest): array
    {
        $contents = $this->savedContents($serverRequest);
        $snapshot = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($snapshot);

        return $snapshot;
    }

    /**
     * Saves one snapshot and answers the raw JSONL bytes it persisted.
     */
    private function savedContents(ServerRequestInterface $serverRequest): string
    {
        $id = new ErrorReplayStore($this->directory)->save(
            $serverRequest,
            new RuntimeException('request failed'),
            400,
            'req-facts',
        );

        $contents = file_get_contents($this->directory . '/' . $id . '.json');
        self::assertIsString($contents);

        return $contents;
    }
}
