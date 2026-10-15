<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The rate-limit policy's integer bounds: the four range invariants the
 * knob set holds, as one assertion pass.
 *
 * A mistyped zero halts the boot with the knob named -- the reference's
 * `max(1, ...)` silently promoted it, which is a limit the operator
 * believes is set while it reads as one. The port's strict reading
 * rejects the value instead; these bounds are the range half of that
 * contract (the spelling half lives in {@see StrictIntegers}).
 */
final class RateLimitBounds
{
    private const int MAX_PORT = 65535;

    /**
     * Asserts the four bounded knobs' ranges.
     *
     * @param int $limit     the request budget per window
     * @param int $window    the window length in seconds
     * @param int $maxKeys   the bucket-table capacity bound
     * @param int $redisPort the redis store's endpoint port
     *
     * @throws HttpException when a bound is violated
     */
    public static function assert(int $limit, int $window, int $maxKeys, int $redisPort): void
    {
        if ($limit < 1) {
            throw new HttpException('The security.rate_limit limit knob must be 1 or above.');
        }

        if ($window < 1) {
            throw new HttpException('The security.rate_limit window knob must be 1 or above.');
        }

        if ($maxKeys < 1) {
            throw new HttpException('The security.rate_limit max_keys knob must be 1 or above.');
        }

        if ($redisPort < 1 || $redisPort > self::MAX_PORT) {
            throw new HttpException(
                'The security.rate_limit redis_port knob must be between 1 and 65535.',
            );
        }
    }
}
