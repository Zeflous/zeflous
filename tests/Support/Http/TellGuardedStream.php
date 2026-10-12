<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use LogicException;
use Override;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic stream double for the non-seekable emission path: answers
 * a configurable size, refuses every positional answer loudly (a tell()
 * or rewind() call on a stream that reported itself unseekable is a
 * contract violation worth crashing on), and reads one chunk then
 * nothing. The unseekable stream is the reason the emitter neither
 * rewinds it nor consults its cursor: the remaining Content-Length falls
 * back to the whole size.
 */
final class TellGuardedStream implements StreamInterface
{
    public function __construct(private readonly ?int $size)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return 'guarded';
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

    #[Override]
    public function getSize(): ?int
    {
        return $this->size;
    }

    #[Override]
    public function tell(): int
    {
        throw new LogicException('tell() must not be called on the guarded stream.');
    }

    #[Override]
    public function eof(): bool
    {
        return false;
    }

    #[Override]
    public function isSeekable(): bool
    {
        return false;
    }

    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        throw new LogicException('seek() must not be called on the guarded stream.');
    }

    #[Override]
    public function rewind(): void
    {
        throw new LogicException('rewind() must not be called on the guarded stream.');
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
