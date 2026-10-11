<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Deterministic stream wrapper that reports itself seekable in its metadata
 * (the PHP engine marks every userland wrapper seekable) while its actual
 * `fseek()` fails -- reproducing a stream whose capabilities and its engine
 * behaviour disagree, the exact shape that must fail closed rather than
 * pretend to rewind. Reads yield the payload 'data' exactly once.
 */
final class NoSeekStreamWrapper
{
    private const string PAYLOAD = 'data';

    /** @var null|resource */
    public $context;

    private bool $read = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        if ($this->read) {
            return '';
        }
        $this->read = true;

        return self::PAYLOAD;
    }

    public function stream_write(string $data): int
    {
        return 4;
    }

    public function stream_eof(): bool
    {
        return $this->read;
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => 4];
    }
}
