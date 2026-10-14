<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Minimal userland stream wrapper used by {@see \Zef\Test\Http\StreamSchemeTest}
 * to prove that a scheme registered in this process is permitted by the
 * opener's allow-list, while a built-in transport is not.
 */
final class NullStreamWrapper
{
    /** @var null|resource */
    public $context;

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
        return mb_strlen($data);
    }

    public function stream_eof(): bool
    {
        return true;
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => 0];
    }
}
