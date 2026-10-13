<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Deterministic non-seekable body double: yields its payload exactly
 * once, answers an unknown size, and reports itself non-seekable -- the
 * shape of a piped request body the SAPI hands a worker (php://input on
 * a chunked request, a stdin pipe). Position queries and seeks throw per
 * the PSR-7 contract a stream that cannot seek honours, so the ingress
 * gate's restore-cursor path is observable through what the guard
 * returns rather than through any cursor the double could report.
 */
final class NonSeekableBody implements StreamInterface
{
    private bool $delivered = false;

    public function __construct(private readonly string $payload)
    {
    }

    #[Override]
    public function __toString(): string
    {
        try {
            return $this->getContents();
        } catch (RuntimeException) {
            return '';
        }
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
        throw new RuntimeException('Non-seekable body stream reports no position.');
    }

    #[Override]
    public function eof(): bool
    {
        return $this->delivered;
    }

    #[Override]
    public function isSeekable(): bool
    {
        return false;
    }

    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        throw new RuntimeException('Non-seekable body stream cannot seek.');
    }

    #[Override]
    public function rewind(): void
    {
        throw new RuntimeException('Non-seekable body stream cannot rewind.');
    }

    #[Override]
    public function isWritable(): bool
    {
        return false;
    }

    #[Override]
    public function write(string $string): int
    {
        throw new RuntimeException('Non-seekable body stream is not writable.');
    }

    #[Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[Override]
    public function read(int $length): string
    {
        if ($this->delivered) {
            return '';
        }

        $this->delivered = true;

        return $this->payload;
    }

    #[Override]
    public function getContents(): string
    {
        if ($this->delivered) {
            return '';
        }

        $this->delivered = true;

        return $this->payload;
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return null;
    }
}
