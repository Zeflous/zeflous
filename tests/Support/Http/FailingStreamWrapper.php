<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Deterministic stream wrapper whose engine reads and writes fail: a
 * `stream_write`/`stream_read` returning false makes `fwrite()`/`fread()`
 * return false on the real call path (verified against the PHP engine),
 * which exercises the stream wrapper's transport-failure branches with the
 * genuine engine behaviour instead of a stubbed exception.
 */
final class FailingStreamWrapper
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

    public function stream_read(int $count): false|string
    {
        return $count === 0 ? '' : false;
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_stat(): false
    {
        return false;
    }
}
