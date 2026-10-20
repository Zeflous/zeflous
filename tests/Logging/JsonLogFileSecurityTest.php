<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Logging\JsonLogFileSecurity;
use Zef\Framework\Logging\JsonLogWriteException;

/**
 * The opened-file security check's own refusal grammar: the file that
 * answers on disk must be the file the stream holds (same device, same
 * inode, no symlink in between), the permissions must tighten to
 * owner-only, and the byte offset a partial record rolls back to must
 * be the file's own size -- zero for an empty log, the exact byte count
 * for one that ends mid-record.
 *
 * @internal
 */
#[CoversClass(JsonLogFileSecurity::class)]
final class JsonLogFileSecurityTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-logsec-' . bin2hex(random_bytes(8));
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

    /**
     * The refusal the short-circuit owes its existence to: when the path
     * vanishes between the open and the check, lstat answers false and
     * the OR-chain stops at the second clause -- the stream's own facts
     * are never consulted. A chain that merged or reordered the first
     * clauses would fall through to the device comparison, read the
     * boolean as an array offset, and answer null against the stream's
     * real device -- the same exception, one engine warning louder. The
     * warning ledger is the observable: exactly one warning, the
     * lstat one, nothing else.
     */
    public function testAVanishedPathIsRefusedWhileTheStreamStillHoldsItsFile(): void
    {
        $file = $this->directory . '/vanished.jsonl';
        self::assertTrue(touch($file));
        $stream = fopen($file, 'rb');
        self::assertIsResource($stream);
        unlink($file);

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        });

        try {
            JsonLogFileSecurity::secure($stream, $file);
            self::fail('A vanished path must be refused.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame(
                'The JSON log destination changed while it was being opened.',
                $jsonLogWriteException->getMessage(),
            );
        } finally {
            restore_error_handler();
            fclose($stream);
        }

        self::assertCount(1, $warnings);
        self::assertStringStartsWith('lstat(): Lstat failed for', $warnings[0]);
    }

    public function testAnEmptyFileRollsBackToOffsetZero(): void
    {
        $file = $this->directory . '/empty.jsonl';
        self::assertTrue(touch($file));
        $stream = fopen($file, 'rb');
        self::assertIsResource($stream);

        try {
            self::assertSame(0, JsonLogFileSecurity::secure($stream, $file));
        } finally {
            fclose($stream);
        }

        self::assertSame(0o600, fileperms($file) & 0o777);
    }

    public function testAPartialRecordRollsBackToItsOwnByteCount(): void
    {
        $file = $this->directory . '/partial.jsonl';
        self::assertSame(12, file_put_contents($file, '{"partial":' . "\n"));
        self::assertTrue(chmod($file, 0o644));
        $stream = fopen($file, 'rb');
        self::assertIsResource($stream);

        try {
            self::assertSame(12, JsonLogFileSecurity::secure($stream, $file));
        } finally {
            fclose($stream);
        }

        self::assertSame(0o600, fileperms($file) & 0o777);
    }
}
