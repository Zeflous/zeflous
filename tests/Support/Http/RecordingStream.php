<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic stream double that delegates every answer to a real
 * stream while recording the chunk lengths its reads are asked for. The
 * emitter's fixed-size chunk constant is only observable through the
 * sequence of read lengths -- the echoed output is byte-identical for any
 * chunk size -- so this double is the mutant anchor for that constant.
 */
final class RecordingStream implements StreamInterface
{
    /** @var list<int> */
    public array $readLengths = [];

    public function __construct(private readonly StreamInterface $inner)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->inner;
    }

    #[Override]
    public function read(int $length): string
    {
        $this->readLengths[] = $length;

        return $this->inner->read($length);
    }

    #[Override]
    public function close(): void
    {
        $this->inner->close();
    }

    #[Override]
    public function detach(): mixed
    {
        return $this->inner->detach();
    }

    #[Override]
    public function getSize(): ?int
    {
        return $this->inner->getSize();
    }

    #[Override]
    public function tell(): int
    {
        return $this->inner->tell();
    }

    #[Override]
    public function eof(): bool
    {
        return $this->inner->eof();
    }

    #[Override]
    public function isSeekable(): bool
    {
        return $this->inner->isSeekable();
    }

    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        $this->inner->seek($offset, $whence);
    }

    #[Override]
    public function rewind(): void
    {
        $this->inner->rewind();
    }

    #[Override]
    public function isWritable(): bool
    {
        return $this->inner->isWritable();
    }

    #[Override]
    public function write(string $string): int
    {
        return $this->inner->write($string);
    }

    #[Override]
    public function isReadable(): bool
    {
        return $this->inner->isReadable();
    }

    #[Override]
    public function getContents(): string
    {
        return $this->inner->getContents();
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return $this->inner->getMetadata($key);
    }
}
