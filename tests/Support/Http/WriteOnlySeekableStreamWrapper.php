<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Deterministic seekable write-only stream wrapper: opened with mode 'wb'
 * (so the PSR-7 stream answers its readability question with false) while
 * its seek and tell keep working -- the shape that distinguishes
 * "__toString must not rewind" from "cannot rewind at all".
 */
final class WriteOnlySeekableStreamWrapper
{
    /** @var null|resource */
    public $context;

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        return '';
    }

    public function stream_write(string $data): int
    {
        return 4;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        $this->position = $offset;

        return true;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => 0];
    }
}
