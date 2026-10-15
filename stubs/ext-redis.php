<?php

declare(strict_types=1);

/*
 * Static-analysis stub for the optional phpredis extension (`ext-redis`,
 * suggested in composer.json): the three commands plus the connection the
 * shared rate-limit family's boundary calls. The stub keeps phpstan and
 * psalm honest in environments that do not carry the extension -- the
 * boundary's union member carries the extension's own `\Redis` in
 * production (engine-checked against the real class wherever the runner
 * carries it), while the interface arm ({@see RedisConnectionInterface})
 * is the family's own seam, implemented by the test suite's real class
 * double. The reply shapes match the RedisCommandsInterface seam.
 */

namespace {

    class Redis
    {
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
