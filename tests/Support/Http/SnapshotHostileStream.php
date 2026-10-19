<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use LogicException;
use Override;
use Psr\Http\Message\StreamInterface;

/**
 * Deterministic stream double for the snapshot dance's failure modes:
 * the snapshot must answer null -- and restore the cursor exactly when
 * it managed to read it -- whichever leg of the tell / rewind /
 * getContents / seek dance fails. The double answers a fixed, distinctive
 * position (7) so the restore's argument is assertable, records every
 * sought offset for the same reason, and fails exactly the legs the test
 * configures while the rest behave.
 */
final class SnapshotHostileStream implements StreamInterface
{
    /** The fixed cursor position the double always reports. */
    public const int POSITION = 7;

    /** @var list<int> the offsets every seek() call received */
    public array $soughtOffsets = [];

    public function __construct(
        private readonly ?int $size = null,
        private readonly bool $failTell = false,
        private readonly bool $failRead = false,
        private readonly bool $failSeek = false,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return 'hostile';
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
        if ($this->failTell) {
            throw new LogicException('The position cannot be read.');
        }

        return self::POSITION;
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
        $this->soughtOffsets[] = $offset;

        if ($this->failSeek) {
            throw new LogicException('The position cannot be restored.');
        }
    }

    #[Override]
    public function rewind(): void
    {
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
        if ($this->failRead) {
            throw new LogicException('The contents cannot be read.');
        }

        return 'captured-body';
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return null;
    }
}
