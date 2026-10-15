<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use RuntimeException;
use Zef\Framework\Http\RedisConnectionInterface;

/**
 * The phpredis boundary's class double: a real, autoloaded, fully
 * analysed implementor of the connection seam, one behaviour in every
 * environment -- where the runner carries the extension the production
 * path speaks to the extension's own `\Redis` exactly as phpredis
 * declares it, and this double never touches that class at all; where
 * the runner does not, the double is the client the suite injects.
 *
 * The double records every call into `$calls` and answers the
 * connection from the `$connectResult` / `$connectThrows` state the
 * tests configure, which is the contract the boundary needs: one
 * connection attempt, three one-line delegations, both failure arms of
 * the establishment.
 *
 * @internal
 */
final class RedisDouble implements RedisConnectionInterface
{
    public bool $connectResult = true;

    public bool $connectThrows = false;

    /** @var list<array<int, int|string>> */
    public array $calls = [];

    #[Override]
    public function connect(string $host, int $port): bool
    {
        $this->calls[] = ['connect', $host, $port];

        if ($this->connectThrows) {
            throw new RuntimeException('the connection attempt threw');
        }

        return $this->connectResult;
    }

    #[Override]
    public function incr(string $key): int
    {
        $this->calls[] = ['incr', $key];

        return 1;
    }

    #[Override]
    public function ttl(string $key): int
    {
        $this->calls[] = ['ttl', $key];

        return 60;
    }

    #[Override]
    public function expire(string $key, int $seconds): void
    {
        $this->calls[] = ['expire', $key, $seconds];
    }
}
