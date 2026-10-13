<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic lying-size stream double: delegates every answer and
 * every mutation to a real inner stream, except `getSize()`, which
 * answers the configured size -- the shape of a stream whose stated
 * size understates its deliverable bytes. The ingress gate's read-ahead
 * probe exists precisely because such a stream may exist: the eager
 * claim and the stated size both pass while the delivered bytes still
 * cross the policy.
 */
final class LyingSizeStream implements StreamInterface
{
    public function __construct(
        private readonly StreamInterface $inner,
        private readonly ?int $reportedSize,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->inner->__toString();
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
        return $this->reportedSize;
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
    public function read(int $length): string
    {
        return $this->inner->read($length);
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
