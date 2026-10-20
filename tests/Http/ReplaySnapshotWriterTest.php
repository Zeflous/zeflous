<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ReplaySnapshotException;
use Zef\Framework\Http\ReplaySnapshotWriter;
use Zef\Test\Support\Http\ReplaySnapshotDoubleState;
use Zef\Test\Support\Http\ReplaySnapshotFunctionDoubles;

/**
 * Pins the snapshot writer's own persistence contract: the exact bytes on
 * disk (the document followed by one newline), the exclusive-file refusal
 * the store's battery already covers, and -- through the boundary's
 * function doubles -- the failure containment the engine cannot be made to
 * produce on demand (a chmod refusal, a zero-byte write, a short write
 * budget), whose lock hygiene and partial-file removal are the writer's
 * whole reason to exist.
 *
 * @internal
 */
#[CoversClass(ReplaySnapshotWriter::class)]
final class ReplaySnapshotWriterTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-snapshot-w-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        ReplaySnapshotFunctionDoubles::define();
    }

    #[Override]
    protected function tearDown(): void
    {
        ReplaySnapshotFunctionDoubles::reset();

        $files = glob($this->directory . '/*');
        foreach (\is_array($files) ? $files : [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        if (!is_dir($this->directory)) {
            return;
        }

        rmdir($this->directory);
    }

    public function testWritesTheDocumentFollowedByExactlyOneNewline(): void
    {
        ReplaySnapshotWriter::write($this->directory, str_repeat('a', 32), '{"label":"persisted"}');

        self::assertSame(
            "{\"label\":\"persisted\"}\n",
            file_get_contents($this->directory . '/' . str_repeat('a', 32) . '.json'),
        );
    }

    public function testReleasesTheAdvisoryLockOnTheSuccessPath(): void
    {
        ReplaySnapshotWriter::write($this->directory, str_repeat('b', 32), '{"ok":true}');

        self::assertSame(
            [\LOCK_EX, \LOCK_UN],
            array_map(static fn (array $call): int => $call[0], ReplaySnapshotDoubleState::$flockCalls),
            'A completed snapshot must release its advisory lock before the stream closes.',
        );
    }

    public function testAChmodRefusalIsContainedWithoutReleasingALockNeverTaken(): void
    {
        ReplaySnapshotDoubleState::$chmodAnswer = false;

        try {
            ReplaySnapshotWriter::write($this->directory, str_repeat('c', 32), '{"ok":true}');
            self::fail('A chmod refusal must fail closed.');
        } catch (ReplaySnapshotException $replaySnapshotException) {
            self::assertSame('Unable to secure the replay snapshot file.', $replaySnapshotException->getMessage());
        }

        self::assertSame(
            [],
            array_map(static fn (array $call): int => $call[0], ReplaySnapshotDoubleState::$flockCalls),
            'No lock was taken when the write failed at chmod, so none may be released.',
        );
        self::assertFileDoesNotExist($this->directory . '/' . str_repeat('c', 32) . '.json');
    }

    public function testAZeroByteWriteIsRefusedAndRemoved(): void
    {
        ReplaySnapshotDoubleState::$fwriteAnswers = [0];

        try {
            ReplaySnapshotWriter::write($this->directory, str_repeat('d', 32), '{"ok":true}');
            self::fail('A zero-byte write must be refused.');
        } catch (ReplaySnapshotException $replaySnapshotException) {
            self::assertSame(
                'Unable to write a complete replay snapshot.',
                $replaySnapshotException->getMessage(),
            );
        }

        self::assertFileDoesNotExist($this->directory . '/' . str_repeat('d', 32) . '.json');
    }

    public function testAWriteRefusalReleasesTheAdvisoryLockItTook(): void
    {
        ReplaySnapshotDoubleState::$fwriteAnswers = [0];

        try {
            ReplaySnapshotWriter::write($this->directory, str_repeat('e', 32), '{"ok":true}');
            self::fail('A write refusal must fail closed.');
        } catch (ReplaySnapshotException) {
            // The message is pinned by the zero-byte test above.
        }

        self::assertSame(
            [\LOCK_EX, \LOCK_UN],
            array_map(static fn (array $call): int => $call[0], ReplaySnapshotDoubleState::$flockCalls),
            'The lock the writer took must be released on the failure path before the stream closes.',
        );
    }

    public function testTheLoopBoundCountsCharactersAndSlicesOnCharacterOffsets(): void
    {
        ReplaySnapshotDoubleState::$fwriteChunk = 1;

        ReplaySnapshotWriter::write($this->directory, str_repeat('7', 32), '{"label":"é"}');

        $payloads = array_map(
            static fn (array $call): string => $call[0],
            ReplaySnapshotDoubleState::$fwriteCalls,
        );
        $contents = '{"label":"é"}' . "\n";

        self::assertCount(
            mb_strlen($contents),
            $payloads,
            'The loop bound is the character count, so a one-byte-per-call budget needs exactly that many calls.',
        );
        self::assertSame(
            mb_substr($contents, 11),
            $payloads[11],
            'The write cursor advances on character offsets, so the slice past the multibyte label starts at '
            . "the closing quote, not the label's second byte.",
        );
    }
}
