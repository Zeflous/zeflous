<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Deterministic stream wrapper whose open SUCCEEDS while emitting a
 * deprecation-grade diagnostic: the userland trigger_error() routes
 * through the active error handler with a severity the diagnostics
 * capture must refuse (E_USER_DEPRECATED is neither E_WARNING nor
 * E_NOTICE), which is the distinction a suite can only observe when a
 * wrapper hands it over directly -- the engine itself never emits a
 * deprecation from fopen() the way this seam's tests need it to.
 */
final class DeprecatingStreamWrapper
{
    /** @var null|resource */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        @trigger_error('The wrapper deprecated ' . $path . '.', \E_USER_DEPRECATED);

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
