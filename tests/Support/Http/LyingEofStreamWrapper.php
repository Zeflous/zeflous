<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * Deterministic stream wrapper that lies about the end of the stream: its
 * eof answers false forever while its reads hand out the payload exactly
 * once and then run empty (verified against the PHP engine -- fread()
 * answers the payload, then an empty string, without looping). This is the
 * engine disagreement the upload move loop's defensive break guards: a
 * chunk that reads empty below the not-at-eof condition must end the copy
 * rather than spin.
 */
final class LyingEofStreamWrapper
{
    private const string PAYLOAD = 'AB';

    /** @var null|resource */
    public $context;

    private bool $payloadYielded = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        if ($this->payloadYielded) {
            return '';
        }

        $this->payloadYielded = true;

        return self::PAYLOAD;
    }

    public function stream_write(string $data): int
    {
        return 0;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return true;
    }

    public function stream_tell(): int
    {
        return 0;
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => 2];
    }
}
