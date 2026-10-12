<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic adversarial stream double: reports itself seekable while
 * its rewind() is a lie that moves nothing, answers a configurable cursor
 * position and size, and reads one chunk then nothing.
 *
 * The reference implementation measures the reconcilable Content-Length
 * from the stream's live cursor precisely because such an implementation
 * may exist -- the emitter rewinds first, but a rewind that does not move
 * the cursor must leave a remainder the arithmetic still honours. This
 * double pins that arithmetic: a size of 5 with a cursor parked at 2 owes
 * exactly 3 remaining octets.
 */
final class CursoredStream implements StreamInterface
{
    public int $tellCalls = 0;

    public function __construct(
        private readonly ?int $size,
        private readonly int $position,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return '';
    }

    #[Override]
    public function close(): void
    {
        // The double holds no engine resource; there is nothing to close.
    }

    #[Override]
    public function detach(): mixed
    {
        return null;
    }

    /**
     * Answers the configured size, or null when the double was built
     * to model an unmeasurable stream.
     */
    #[Override]
    public function getSize(): ?int
    {
        return $this->size;
    }

    #[Override]
    public function tell(): int
    {
        ++$this->tellCalls;

        return $this->position;
    }

    #[Override]
    public function eof(): bool
    {
        return false;
    }

    #[Override]
    public function isSeekable(): bool
    {
        return true;
    }

    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        // Deliberately a no-op: the adversarial point of this double is
        // the cursor that never moves, not the seek it would serve.
    }

    #[Override]
    public function rewind(): void
    {
        // Deliberately a no-op: the adversarial point of this double.
    }

    #[Override]
    public function isWritable(): bool
    {
        return false;
    }

    #[Override]
    public function write(string $string): int
    {
        return 0;
    }

    #[Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[Override]
    public function read(int $length): string
    {
        return '';
    }

    #[Override]
    public function getContents(): string
    {
        return '';
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return null;
    }
}
