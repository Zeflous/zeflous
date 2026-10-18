<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\ErrorReplayStore;
use Zef\Framework\Http\ReplayPayloadSanitizer;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;

/** @internal */
#[CoversClass(ErrorReplayStore::class)]
#[CoversClass(ReplayPayloadSanitizer::class)]
final class ErrorReplayStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-replay-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testStoresOnlySanitizedRequestDataWithPrivateFilePermissions(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, '{"username":"alice","password":"hunter2","nested":{"access_token":"secret123"},"count":3,"enabled":true,"empty":null}');
        rewind($resource);
        $stream = new Stream($resource);
        $request = new ServerRequest(
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

        $id = (new ErrorReplayStore($this->directory))->save(
            $request,
            new RuntimeException('request failed'),
            400,
            'req-replay-1',
        );

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $id);
        self::assertSame(0, $stream->tell(), 'snapshot capture restores the original body cursor');
        $file = $this->directory . '/' . $id . '.json';
        self::assertFileExists($file);
        self::assertSame(0600, fileperms($file) & 0777);

        $contents = file_get_contents($file);
        self::assertIsString($contents);
        self::assertStringNotContainsString('secret123', $contents);
        self::assertStringNotContainsString('hunter2', $contents);
        self::assertStringNotContainsString('Authorization', $contents);

        $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('/users', $snapshot['path']);
        self::assertStringContainsString('filter=active', $snapshot['query']);
        self::assertStringContainsString('token=%5BREDACTED%5D', $snapshot['query']);
        self::assertTrue($snapshot['body_replayable']);
        $body = json_decode($snapshot['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $body['password']);
        self::assertSame('[REDACTED]', $body['nested']['access_token']);
        self::assertSame(3, $body['count']);
        self::assertTrue($body['enabled']);
        self::assertNull($body['empty']);
    }

    public function testSanitizesAndReplaysSupportedTextBodies(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, 'password=hunter2&access_token=secret123');
        rewind($resource);
        $request = new ServerRequest(
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

        $id = (new ErrorReplayStore($this->directory))->save(
            $request,
            new RuntimeException('request failed'),
            500,
            'req-replay-2',
        );
        $snapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertTrue($snapshot['body_replayable']);
        self::assertStringNotContainsString('hunter2', $snapshot['body']);
        self::assertStringNotContainsString('secret123', $snapshot['body']);
        self::assertStringContainsString('[REDACTED]', $snapshot['body']);
    }

    public function testRedactsSensitiveXmlElementsBeforeSaving(): void
    {
        $resource = fopen('php://temp', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, '<user><password>hunter2</password><name>alice</name></user>');
        rewind($resource);
        $request = new ServerRequest(
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

        $id = (new ErrorReplayStore($this->directory))->save(
            $request,
            new RuntimeException('request failed'),
            400,
            'req-replay-xml',
        );
        $snapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $id . '.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertTrue($snapshot['body_replayable']);
        self::assertStringNotContainsString('hunter2', $snapshot['body']);
        self::assertStringContainsString('[REDACTED]', $snapshot['body']);
        self::assertStringContainsString('<name>alice</name>', $snapshot['body']);
    }

    public function testEmptyAndOversizedBodiesHaveExplicitReplayability(): void
    {
        $store = new ErrorReplayStore($this->directory);
        $emptyRequest = new ServerRequest(
            'GET',
            new Uri('https://app.example.test/users'),
        );
        $emptyId = $store->save($emptyRequest, new RuntimeException('empty'), 404, 'empty-req');
        $emptySnapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $emptyId . '.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
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
        $largeId = $store->save($largeRequest, new RuntimeException('large'), 413, 'large-req');
        $largeSnapshot = json_decode(
            (string) file_get_contents($this->directory . '/' . $largeId . '.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertFalse($largeSnapshot['body_replayable']);
        self::assertSame('', $largeSnapshot['body']);
    }

    public function testRefusesReplayStorageInsideDocumentRoot(): void
    {
        $previous = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $publicRoot = $this->directory . '/public';
        $replayDirectory = $publicRoot . '/replays';
        mkdir($publicRoot, 0700);
        mkdir($replayDirectory, 0700);
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        try {
            $this->expectException(\InvalidArgumentException::class);
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
}
