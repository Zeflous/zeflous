<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Deterministic stream wrapper whose open fails by answering false: the
 * engine's fopen() then emits its diagnostic warning and answers false on
 * the real call path (verified against the PHP engine), which is the
 * temporary-target creation failure the upload move must report. The stat
 * side reports every path as a directory so the move's destination guards
 * pass while the generated temporary name never counts as an existing
 * file.
 */
final class UnopenableStreamWrapper
{
    /** @var null|resource */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
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
