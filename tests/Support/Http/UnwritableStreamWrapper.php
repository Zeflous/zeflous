<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Deterministic stream wrapper whose writes fail: a `stream_write`
 * answering false makes `fwrite()` answer false on the real call path
 * (verified against the PHP engine), which is the payload-copy failure the
 * upload move must report as its own closed failure. The stat side reports
 * every path as a directory so the move's destination guards pass while
 * the cleanup never sees a leftover temporary file.
 */
final class UnwritableStreamWrapper
{
    /** @var null|resource */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): false|int
    {
        return $data === '' ? 0 : false;
    }

    public function stream_read(int $count): string
    {
        return '';
    }

    public function stream_eof(): bool
    {
        return true;
    }

    /**
     * @return array<int|string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0o040777,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1,
        ];
    }
}
