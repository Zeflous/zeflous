<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic stream double for the unknown-size path: a seekable
 * stream whose size cannot be read at all (the PSR-7 unknown-size
 * spelling, `getSize()` null), reading as empty at rest -- the one
 * body shape whose length no measurement can name (issue #801's
 * finding 1's own edge, the view's honest zero fallback).
 */
final class SizelessStream implements StreamInterface
{
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

    #[Override]
    public function getSize(): ?int
    {
        return null;
    }

    #[Override]
    public function tell(): int
    {
        return 0;
    }

    #[Override]
    public function eof(): bool
    {
        return true;
    }

    #[Override]
    public function isSeekable(): bool
    {
        return true;
    }

    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        // The double rests at zero; any seek lands there too.
    }

    #[Override]
    public function rewind(): void
    {
        // The double rests at zero by construction.
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
