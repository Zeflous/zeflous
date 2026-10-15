<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Zef\Framework\Http\RedisCommandsInterface;

/**
 * Deterministic Redis commands double: records every command with its
 * arguments and answers from the per-command returns the test configures
 * (defaulting to the replies a brand-new bucket produces).
 */
final class RecordingRedisCommands implements RedisCommandsInterface
{
    /** @var list<array{string, array<int, int|string>}> */
    public array $calls = [];

    public int|string $incr = 1;

    public false|int $ttl = -2;

    #[Override]
    public function incr(string $key): int|string
    {
        $this->calls[] = ['incr', [$key]];

        return $this->incr;
    }

    #[Override]
    public function ttl(string $key): false|int
    {
        $this->calls[] = ['ttl', [$key]];

        return $this->ttl;
    }

    #[Override]
    public function expire(string $key, int $seconds): void
    {
        $this->calls[] = ['expire', [$key, $seconds]];
    }
}
