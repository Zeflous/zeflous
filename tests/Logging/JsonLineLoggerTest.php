<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Logging\LogContextSanitizer;

/**
 * @internal
 */
#[CoversClass(JsonLineLogger::class)]
#[CoversClass(LogContextSanitizer::class)]
final class JsonLineLoggerTest extends TestCase
{
    private ?string $path = null;

    protected function tearDown(): void
    {
        if ($this->path !== null && (is_file($this->path) || is_link($this->path))) {
            unlink($this->path);
        }
    }

    public function testWritesOneStructuredAndRedactedJsonRecord(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $logger = new JsonLineLogger($this->path);

        $logger->warning('Request failed for token=secret123', [
            'request_id' => 'req-123',
            'password' => 'hunter2',
            'exception' => new RuntimeException('authorization=Bearer secret123'),
        ]);

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('warning', $record['level']);
        self::assertSame('Request failed for token=[REDACTED]', $record['message']);
        self::assertSame('req-123', $record['context']['request_id']);
        self::assertSame('[REDACTED]', $record['context']['password']);
        self::assertSame(RuntimeException::class, $record['context']['exception']['class']);
        self::assertStringNotContainsString('secret123', $lines[0]);
        self::assertStringNotContainsString('hunter2', $lines[0]);
    }

    public function testSanitizesNestedAndUnsupportedContextValues(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $logger = new JsonLineLogger($this->path);
        $nested = ['token' => 'private', 'child' => ['value' => 'visible']];
        for ($depth = 0; $depth < 7; ++$depth) {
            $nested = ['next' => $nested];
        }

        $logger->info('safe', [
            'count' => 5,
            'ratio' => 1.5,
            'enabled' => true,
            'empty' => null,
            'nested' => $nested,
            'object' => new \stdClass(),
            4 => 'numeric key',
        ]);

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        $record = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(5, $record['context']['count']);
        self::assertSame(1.5, $record['context']['ratio']);
        self::assertTrue($record['context']['enabled']);
        self::assertNull($record['context']['empty']);
        self::assertSame('[stdClass]', $record['context']['object']);
        self::assertSame('numeric key', $record['context']['4']);
        self::assertTrue($record['context']['nested']['next']['next']['next']['_truncated']);
        self::assertStringNotContainsString('private', $lines[0]);
    }

    public function testRejectsUnknownLevels(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $logger = new JsonLineLogger($this->path);

        try {
            $logger->log('verbose', 'not a PSR-3 level');
            self::fail('Unknown levels must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Unknown PSR-3 log level.', $exception->getMessage());
        }
    }

    public function testRejectsSymlinkDestinations(): void
    {
        $target = sys_get_temp_dir() . '/zef-json-log-target-' . bin2hex(random_bytes(8)) . '.jsonl';
        $this->path = sys_get_temp_dir() . '/zef-json-log-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($target, '');
        if (!symlink($target, $this->path)) {
            @unlink($target);
            self::markTestSkipped('Symlinks are not available in this environment.');
        }

        try {
            new JsonLineLogger($this->path);
            self::fail('Symlink destinations must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertFileExists($target);
        } finally {
            @unlink($target);
        }
    }
}
