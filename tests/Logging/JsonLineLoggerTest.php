<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Zef\Framework\Logging\JsonLineFormatter;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Logging\JsonLineRecordWriter;
use Zef\Framework\Logging\JsonLineWriter;
use Zef\Framework\Logging\JsonLogDestination;
use Zef\Framework\Logging\JsonLogDocumentRoot;
use Zef\Framework\Logging\JsonLogFilePrivacy;
use Zef\Framework\Logging\JsonLogFileSecurity;
use Zef\Framework\Logging\JsonLogWriteException;
use Zef\Framework\Logging\LogContextSanitizer;
use Zef\Test\Support\Http\UnopenableStreamWrapper;

/**
 * @internal
 */
#[CoversClass(JsonLineLogger::class)]
#[CoversClass(JsonLineFormatter::class)]
#[CoversClass(LogContextSanitizer::class)]
#[CoversClass(JsonLogDestination::class)]
#[CoversClass(JsonLogDocumentRoot::class)]
#[CoversClass(JsonLogFilePrivacy::class)]
#[CoversClass(JsonLineWriter::class)]
#[CoversClass(JsonLogFileSecurity::class)]
#[CoversClass(JsonLineRecordWriter::class)]
final class JsonLineLoggerTest extends TestCase
{
    private const string UNOPENABLE_PROTOCOL = 'zef-unopenable-log';

    private ?string $path = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->path === null || (!is_file($this->path) && !is_link($this->path))) {
            return;
        }

        unlink($this->path);
    }

    public function testWritesOneStructuredAndRedactedJsonRecord(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->warning('Request failed for token=secret123', [
            'request_id' => 'req-123',
            'password' => 'hunter2',
            'exception' => new RuntimeException('authorization=Bearer secret123'),
        ]);

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame('warning', $record['level']);
        self::assertSame('Request failed for token=[REDACTED]', $record['message']);
        $context = $record['context'] ?? null;
        self::assertIsArray($context);
        self::assertSame('req-123', $context['request_id']);
        self::assertSame('[REDACTED]', $context['password']);
        $exception = $context['exception'] ?? null;
        self::assertIsArray($exception);
        self::assertSame(RuntimeException::class, $exception['class']);
        self::assertStringNotContainsString('secret123', $lines[0]);
        self::assertStringNotContainsString('hunter2', $lines[0]);
    }

    public function testSanitizesNestedAndUnsupportedContextValues(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);
        $nested = ['token' => 'private', 'child' => ['value' => 'visible']];
        for ($depth = 0; $depth < 7; ++$depth) {
            $nested = ['next' => $nested];
        }

        $jsonLineLogger->info('safe', [
            'count' => 5,
            'ratio' => 1.5,
            'enabled' => true,
            'empty' => null,
            'nested' => $nested,
            'object' => new stdClass(),
        ]);

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        $context = $record['context'] ?? null;
        self::assertIsArray($context);
        self::assertSame(5, $context['count']);
        self::assertSame(1.5, $context['ratio']);
        self::assertTrue($context['enabled']);
        self::assertNull($context['empty']);
        self::assertSame('[stdClass]', $context['object']);
        self::assertSame(
            '{"4":"numeric key"}',
            json_encode(LogContextSanitizer::sanitize([4 => 'numeric key'])),
        );
        self::assertStringContainsString('"_truncated":true', $lines[0]);
        self::assertStringNotContainsString('private', $lines[0]);
    }

    public function testRejectsUnknownLevels(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        try {
            $jsonLineLogger->log('verbose', 'not a PSR-3 level');
            self::fail('Unknown levels must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Unknown PSR-3 log level.', $invalidArgumentException->getMessage());
        }
    }

    public function testCreatesLogFilesWithOwnerOnlyPermissions(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';

        new JsonLineLogger($this->path)->info('private record');

        $permissions = fileperms($this->path);
        self::assertIsInt($permissions);
        self::assertSame(0, $permissions & 0o077);
        self::assertStringEndsWith("\n", (string) file_get_contents($this->path));
        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
    }

    public function testFailsClosedWhenConfiguredDocumentRootCannotBeResolved(): void
    {
        $missingRoot = sys_get_temp_dir() . '/zef-missing-root-' . bin2hex(random_bytes(8));
        $hadRoot = \array_key_exists('DOCUMENT_ROOT', $_SERVER);
        $previousRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $missingRoot;

        try {
            $this->expectException(InvalidArgumentException::class);
            new JsonLineLogger(sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl');
        } finally {
            if ($hadRoot) {
                $_SERVER['DOCUMENT_ROOT'] = $previousRoot;
            } else {
                unset($_SERVER['DOCUMENT_ROOT']);
            }
        }
    }

    public function testRejectsLogPathsInsideDocumentRoot(): void
    {
        $root = sys_get_temp_dir() . '/zef-public-root-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0o700));
        $hadRoot = \array_key_exists('DOCUMENT_ROOT', $_SERVER);
        $previousRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $root;

        try {
            $this->expectException(InvalidArgumentException::class);
            new JsonLineLogger($root . '/errors.jsonl');
        } finally {
            if ($hadRoot) {
                $_SERVER['DOCUMENT_ROOT'] = $previousRoot;
            } else {
                unset($_SERVER['DOCUMENT_ROOT']);
            }

            rmdir($root);
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
        } catch (InvalidArgumentException) {
            self::assertFileExists($target);
        } finally {
            @unlink($target);
        }
    }

    public function testEveryPsr3LevelWritesItsOwnRecord(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->emergency('level one');
        $jsonLineLogger->alert('level two');
        $jsonLineLogger->critical('level three');
        $jsonLineLogger->error('level four');
        $jsonLineLogger->warning('level five');
        $jsonLineLogger->notice('level six');
        $jsonLineLogger->info('level seven');
        $jsonLineLogger->debug('level eight');

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(8, $lines);

        $records = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            $records[] = $decoded;
        }

        self::assertSame(
            ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            array_column($records, 'level'),
        );
        self::assertSame(
            [
                'level one',
                'level two',
                'level three',
                'level four',
                'level five',
                'level six',
                'level seven',
                'level eight',
            ],
            array_column($records, 'message'),
        );
    }

    public function testRefusesToAppendThroughASymlinkDestination(): void
    {
        $target = sys_get_temp_dir() . '/zef-json-real-' . bin2hex(random_bytes(8)) . '.jsonl';
        $link = sys_get_temp_dir() . '/zef-json-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($target, ''));
        if (!symlink($target, $link)) {
            @unlink($target);
            self::markTestSkipped('Symlinks are not available in this environment.');
        }

        try {
            JsonLineWriter::append($link, '{}');
            self::fail('A symlink destination must be refused at append time.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame(
                'Refusing to write through a symlink log destination.',
                $jsonLogWriteException->getMessage(),
            );
        } finally {
            @unlink($link);
            @unlink($target);
        }
    }

    public function testAnUnopenableDestinationIsRefused(): void
    {
        self::assertTrue(stream_wrapper_register(self::UNOPENABLE_PROTOCOL, UnopenableStreamWrapper::class));

        try {
            set_error_handler(static fn (): bool => true);

            try {
                JsonLineWriter::append(self::UNOPENABLE_PROTOCOL . '://refused.jsonl', '{}');
                self::fail('An unopenable destination must be refused.');
            } finally {
                restore_error_handler();
            }
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame(
                'Unable to open the configured JSON log destination.',
                $jsonLogWriteException->getMessage(),
            );
        } finally {
            stream_wrapper_unregister(self::UNOPENABLE_PROTOCOL);
        }
    }

    public function testAMutatedDestinationIsRefused(): void
    {
        $path = sys_get_temp_dir() . '/zef-json-mutated-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($path, 'first'));
        $stream = fopen($path, 'r+b');
        self::assertIsResource($stream);

        self::assertTrue(unlink($path));
        self::assertNotFalse(file_put_contents($path, 'second'));

        try {
            JsonLogFileSecurity::secure($stream, $path);
            self::fail('A destination replaced underneath the open handle must be refused.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame(
                'The JSON log destination changed while it was being opened.',
                $jsonLogWriteException->getMessage(),
            );
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }

            @unlink($path);
        }
    }

    public function testAnExistingGroupReadableLogDestinationIsRejected(): void
    {
        $path = sys_get_temp_dir() . '/zef-json-priv-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($path, ''));
        self::assertTrue(chmod($path, 0o640));

        try {
            JsonLogFilePrivacy::assertExistingFileIsPrivate($path);
            self::fail('A group-readable destination must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The existing JSON log file must be writable with owner-only permissions.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            @chmod($path, 0o600);
            @unlink($path);
        }
    }

    public function testAnUnwritableLogDestinationIsRejected(): void
    {
        $path = sys_get_temp_dir() . '/zef-json-priv-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($path, ''));
        self::assertTrue(chmod($path, 0o400));

        try {
            JsonLogFilePrivacy::assertExistingFileIsPrivate($path);
            self::fail('A read-only destination must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The existing JSON log file must be writable with owner-only permissions.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            @chmod($path, 0o600);
            @unlink($path);
        }
    }
}
