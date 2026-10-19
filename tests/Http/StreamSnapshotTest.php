<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\StreamSnapshot;
use Zef\Test\Support\Http\SnapshotHostileStream;

/**
 * @internal
 */
#[CoversClass(StreamSnapshot::class)]
final class StreamSnapshotTest extends TestCase
{
    public function testCapturesTheContentsAndRestoresThePosition(): void
    {
        $stream = Stream::fromString('payload');

        self::assertSame('payload', StreamSnapshot::capture($stream));
        self::assertSame(0, $stream->tell());
    }

    public function testAnswersNullAndSkipsTheRestoreWhenThePositionCannotBeRead(): void
    {
        $snapshotHostileStream = new SnapshotHostileStream(failTell: true);

        self::assertNull(StreamSnapshot::capture($snapshotHostileStream));
        self::assertSame([], $snapshotHostileStream->soughtOffsets, 'No position was read, so no restore may run.');
    }

    public function testAnswersNullAndRestoresTheReadPositionWhenTheContentsCannotBeRead(): void
    {
        $snapshotHostileStream = new SnapshotHostileStream(failRead: true);

        self::assertNull(StreamSnapshot::capture($snapshotHostileStream));
        self::assertSame(
            [SnapshotHostileStream::POSITION],
            $snapshotHostileStream->soughtOffsets,
            'The restore must return the cursor to the position read before the failure.',
        );
    }

    public function testAnswersNullWhenThePositionCannotBeRestored(): void
    {
        $snapshotHostileStream = new SnapshotHostileStream(failSeek: true);

        self::assertNull(StreamSnapshot::capture($snapshotHostileStream));
        self::assertSame(
            [SnapshotHostileStream::POSITION],
            $snapshotHostileStream->soughtOffsets,
            'The restore was attempted with the position read before it.',
        );
    }
}
