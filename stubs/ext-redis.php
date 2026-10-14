<?php

declare(strict_types=1);

/*
 * Static-analysis stub for the optional phpredis extension (`ext-redis`,
 * suggested in composer.json): the three commands plus the connection the
 * shared rate-limit store's boundary calls. The stub keeps phpstan and
 * psalm honest in environments that do not carry the extension -- the
 * boundary class itself is verified through a process-isolated eval-
 * defined class double in the test suite. Signatures mirror phpredis;
 * reply shapes match the RedisCommandsInterface seam.
 */

namespace {

    class Redis
    {
        /**
     * The four native commands the shared rate-limit store calls, plus the
     * connection state the process-isolated test double configures (the
     * `stubs/` directory is an analysis artifact: this property exists on
     * the eval-defined `\Redis` double the boundary tests run against, so
     * the tests can flip the connection's answer).
     */
    public static bool $connectResult = true;

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

        public function expire(string $key, int $seconds): void
        {
        }
    }
}
