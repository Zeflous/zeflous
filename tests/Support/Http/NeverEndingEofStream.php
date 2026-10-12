<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic adversarial stream double: its `eof()` answer is a lie
 * that never flips, while its reads serve a fixed payload once and then
 * answer '' forever.
 *
 * The limited input stream's drain loop carries a defensive break on the
 * empty chunk -- the engine contract that makes the loop terminate for a
 * stream whose end-of-file answer is missing or late. That break is only
 * observable against an inner stream whose `eof()` never turns true: with
 * an honest stream the loop's own condition exits first and the break is
 * unreachable, and a mutant that removes the break looks identical. This
 * double pins the break by making it the ONLY way the drain can finish.
 */
final class NeverEndingEofStream implements StreamInterface
{
    /** @var list<int> */
    public array $readLengths = [];

    private int $served = 0;

    public function __construct(private readonly string $payload)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->payload;
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
     * The double's payload always has a length, so the size answer is a
     * plain integer (a covariant narrowing of the contract's `?int`).
     */
    #[Override]
    public function getSize(): int
    {
        return mb_strlen($this->payload, '8bit');
    }

    #[Override]
    public function tell(): int
    {
        return $this->served;
    }

    #[Override]
    public function eof(): bool
    {
        // The adversarial point of this double: the answer never flips.
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
        // Deliberately a no-op: the double models a non-seekable stream.
    }

    #[Override]
    public function rewind(): void
    {
        // Deliberately a no-op: the double models a non-seekable stream.
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
        $this->readLengths[] = $length;

        $remaining = mb_strlen($this->payload, '8bit') - $this->served;
        if ($remaining <= 0) {
            return '';
        }

        $chunk = mb_substr($this->payload, $this->served, min($length, $remaining), '8bit');
        $this->served += mb_strlen($chunk, '8bit');

        return $chunk;
    }

    #[Override]
    public function getContents(): string
    {
        return $this->payload;
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return null;
    }
}
