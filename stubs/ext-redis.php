<?php

declare(strict_types=1);

/*
 * Static-analysis stub for the optional phpredis extension (`ext-redis`,
 * suggested in composer.json): the three commands plus the connection the
 * shared rate-limit store's boundary calls. The stub keeps phpstan and
 * psalm honest in environments that do not carry the extension -- the
 * boundary class itself is verified against an injected anonymous
 * subclass of `\Redis` in the test suite (the real class where the
 * extension is present, an eval-defined shell where it is not). The
 * reply shapes match the RedisCommandsInterface seam, and the declared
 * return types stay narrow enough for the test double's overrides to
 * satisfy the engine against the real extension's signatures.
 */

namespace {

    class Redis
    {
        /**
     * The connection state the injected test double configures (the
     * `stubs/` directory is an analysis artifact: these properties exist
     * on the anonymous `\Redis` subclass the boundary tests run against,
     * so the tests can flip the connection's answer, make it throw, and
     * read every call back).
     */
    public bool $connectResult = true;

    public bool $connectThrows = false;

    /** @var list<array<int, int|string>> */
    public array $calls = [];

    public function connect(string $host, int $port = 6379, float $timeout = 0.0): bool
        {
            return false;
        }

        public function incr(string $key): int|string
        {
            return 0;
        }

        public function ttl(string $key): int|false
        {
            return false;
        }

        public function expire(string $key, int $seconds): bool
        {
            return false;
        }
    }
}
