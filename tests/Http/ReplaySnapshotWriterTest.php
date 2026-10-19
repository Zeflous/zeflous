<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ReplaySnapshotException;
use Zef\Framework\Http\ReplaySnapshotWriter;
use Zef\Test\Support\Http\ReplayStreamWrapper;

/**
 * The snapshot writer's file protocol, pinned end to end: the exact
 * bytes one snapshot occupies on disk, the exclusive-lock discipline
 * (granted, refused, and released on every exit path), and the
 * short-write resume loop's unit semantics -- observed against the real
 * filesystem for the bytes and against a scriptable stream wrapper for
 * the engine calls no real file can be made to make deterministically.
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
        $this->directory = sys_get_temp_dir() . '/zef-replay-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        ReplayStreamWrapper::reset();
        ReplayStreamWrapper::register();
    }

    #[Override]
    protected function tearDown(): void
    {
        ReplayStreamWrapper::unregister();

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

    public function testTheSnapshotIsExactlyOneJsonLine(): void
    {
        ReplaySnapshotWriter::write($this->directory, str_repeat('a', 32), '{"a":1}');

        $contents = file_get_contents($this->directory . '/' . str_repeat('a', 32) . '.json');
        self::assertIsString($contents);
        self::assertSame('{"a":1}' . "\n", $contents);
    }

    public function testAMultibyteSnapshotIsWrittenByteExactly(): void
    {
        ReplaySnapshotWriter::write($this->directory, str_repeat('b', 32), '{"p":"café"}');

        $contents = file_get_contents($this->directory . '/' . str_repeat('b', 32) . '.json');
        self::assertIsString($contents);
        self::assertSame('{"p":"café"}' . "\n", $contents);
    }

    public function testTheFullProtocolLocksWritesUnlocksAndClosesInOrder(): void
    {
        $url = ReplayStreamWrapper::urlFor(str_repeat('c', 32));

        ReplaySnapshotWriter::write(ReplayStreamWrapper::SCHEME . '://replays', str_repeat('c', 32), '{"ok":true}');

        self::assertSame(
            [
                'open:xb',
                'metadata:6',
                'flock:2',
                'write:12',
                'flock:3',
                'close',
            ],
            ReplayStreamWrapper::$calls,
        );
        self::assertSame('{"ok":true}' . "\n", ReplayStreamWrapper::$contents[$url]);
    }

    public function testAZeroByteWriteAbortsTheSnapshotAndReleasesTheLock(): void
    {
        ReplayStreamWrapper::$writeLimits = [0];

        try {
            ReplaySnapshotWriter::write(
                ReplayStreamWrapper::SCHEME . '://replays',
                str_repeat('d', 32),
                '{"ok":true}',
            );
            self::fail('A zero-byte write must abort the snapshot.');
        } catch (ReplaySnapshotException $replaySnapshotException) {
            self::assertSame(
                'Unable to write a complete replay snapshot.',
                $replaySnapshotException->getMessage(),
            );
        }

        self::assertContains('flock:' . \LOCK_EX, ReplayStreamWrapper::$calls);
        self::assertContains('flock:' . \LOCK_UN, ReplayStreamWrapper::$calls);
        self::assertContains('unlink', ReplayStreamWrapper::$calls);
    }

    /**
     * Layer one: the ENGINE's own resume. One fwrite whose stream write
     * reports twelve of fourteen bytes is retried by the engine itself,
     * in BYTE units, until the whole chunk lands -- the writer's own
     * loop never sees the shortfall, and the line completes byte-exactly.
     */
    public function testTheEngineCompletesAShortWriteInByteUnits(): void
    {
        ReplayStreamWrapper::$writeLimits = [12];

        ReplaySnapshotWriter::write(
            ReplayStreamWrapper::SCHEME . '://replays',
            str_repeat('e', 32),
            '{"p":"café"}',
        );

        $url = ReplayStreamWrapper::urlFor(str_repeat('e', 32));
        self::assertSame('{"p":"café"}' . "\n", ReplayStreamWrapper::$contents[$url]);
        self::assertContains('write:12', ReplayStreamWrapper::$calls);
        self::assertContains('write:2', ReplayStreamWrapper::$calls);
        self::assertNotContains('write:0', ReplayStreamWrapper::$calls);
    }

    /**
     * Layer two: the LOOP's resume, which only runs after a write is
     * aborted mid-chunk. The engine wrote bytes 0-11 (twelve: everything
     * through the closing quote) before the abort; the loop then resumes
     * from CHARACTER offset twelve -- the newline -- because its offset
     * counts characters while the engine consumed bytes: byte 12, the
     * closing brace, is the thirteenth character and never lands.
     * A byte-unit resume would send the brace plus newline.
     */
    public function testTheLoopResumesInCharacterUnitsAfterAnAbortedWrite(): void
    {
        ReplayStreamWrapper::$writeLimits = [12, 0];

        ReplaySnapshotWriter::write(
            ReplayStreamWrapper::SCHEME . '://replays',
            str_repeat('f', 32),
            '{"p":"café"}',
        );

        $url = ReplayStreamWrapper::urlFor(str_repeat('f', 32));
        self::assertSame('{"p":"café"' . "\n", ReplayStreamWrapper::$contents[$url]);
        self::assertSame(
            [
                'open:xb',
                'metadata:6',
                'flock:2',
                'write:12',
                'write:0',
                'write:1',
                'flock:3',
                'close',
            ],
            ReplayStreamWrapper::$calls,
        );
    }

    /**
     * The loop's length bound is the CHARACTER count: an aborted write
     * that landed every character (thirteen of them -- the newline is
     * the only byte missing) satisfies the bound and the loop stops
     * there without ever asking for the final byte. A byte-length bound
     * would notice the fourteenth byte missing and go back for it.
     */
    public function testALoopThatReachedEveryCharacterStopsOneByteShort(): void
    {
        ReplayStreamWrapper::$writeLimits = [13, 0];

        ReplaySnapshotWriter::write(
            ReplayStreamWrapper::SCHEME . '://replays',
            str_repeat('g', 32),
            '{"p":"café"}',
        );

        $url = ReplayStreamWrapper::urlFor(str_repeat('g', 32));
        self::assertSame('{"p":"café"}', ReplayStreamWrapper::$contents[$url]);
        self::assertContains('write:13', ReplayStreamWrapper::$calls);
        self::assertNotContains('write:1', ReplayStreamWrapper::$calls);
    }

    /**
     * A character-unit resume from INSIDE the multibyte character
     * re-sends the whole character: the engine consumed byte 10 (the
     * lead byte of the two-byte 'e-acute') before the abort, but the
     * loop's offset reached only CHARACTER 10 -- the closing quote --
     * so the resume sends quote, brace, newline and the lead byte's
     * partner never arrives. Pinned byte-after-byte as shipped; a
     * byte-unit resume would have sent the partner and completed the
     * character instead.
     */
    public function testAResumeFromInsideAMultibyteCharacterResendsTheWholeCharacter(): void
    {
        ReplayStreamWrapper::$writeLimits = [10, 0];

        ReplaySnapshotWriter::write(
            ReplayStreamWrapper::SCHEME . '://replays',
            str_repeat('h', 32),
            '{"p":"café"}',
        );

        $url = ReplayStreamWrapper::urlFor(str_repeat('h', 32));
        // The byte-unit prefix the engine consumed: ten bytes reach the
        // disk, cutting the two-byte 'e-acute' after its lead byte. The
        // offset access is byte-wise by the engine's own contract, and
        // the multibyte-safe fixer cannot rewrite it into character
        // arithmetic the way it rewrote the substr() form this line
        // originally used.
        $payload = '{"p":"café"}';
        $consumedPrefix = '{"p":"caf' . $payload[9];
        self::assertSame($consumedPrefix . '"}' . "\n", ReplayStreamWrapper::$contents[$url]);
        self::assertContains('write:10', ReplayStreamWrapper::$calls);
        self::assertContains('write:3', ReplayStreamWrapper::$calls);
    }

    public function testARefusedLockNeverUnlocksWhatWasNeverLocked(): void
    {
        ReplayStreamWrapper::$lockGranted = false;

        try {
            ReplaySnapshotWriter::write(
                ReplayStreamWrapper::SCHEME . '://replays',
                str_repeat('f', 32),
                '{"ok":true}',
            );
            self::fail('A refused lock must abort the snapshot.');
        } catch (ReplaySnapshotException $replaySnapshotException) {
            self::assertSame(
                'Unable to lock the replay snapshot file.',
                $replaySnapshotException->getMessage(),
            );
        }

        self::assertContains('flock:' . \LOCK_EX, ReplayStreamWrapper::$calls);
        self::assertNotContains('flock:' . \LOCK_UN, ReplayStreamWrapper::$calls);
        self::assertContains('unlink', ReplayStreamWrapper::$calls);
    }
}
