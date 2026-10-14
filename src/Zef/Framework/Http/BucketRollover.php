<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * One fixed-window bucket's accounting: the bucket a request of the
 * current window carries after it is counted -- the existing bucket
 * incremented while its epoch has not passed, a fresh one (count one,
 * reset a full window away) when it has, or when the key never carried
 * one at all.
 *
 * The fixed window's own rule, extracted as its own unit: the window
 * starts when the key's first request arrives and resets whole at the
 * stored epoch -- never slid, never partial.
 */
final class BucketRollover
{
    /**
     * Answers the key's bucket after one accounted request.
     *
     * @param null|array{count:int, reset:int} $existing      the key's stored
     *                                                        bucket, null when
     *                                                        absent
     * @param int                              $windowSeconds the window
     *                                                        length in seconds
     * @param int                              $now           the caller's epoch, Unix
     *                                                        seconds
     *
     * @return array{count:int, reset:int}
     */
    public static function advanced(?array $existing, int $windowSeconds, int $now): array
    {
        $bucket = self::current($existing, $windowSeconds, $now);
        ++$bucket['count'];

        return $bucket;
    }

    /**
     * The bucket the key's current window carries: the existing one while
     * its epoch has not passed, a fresh one otherwise.
     *
     * @param null|array{count:int, reset:int} $existing
     *
     * @return array{count:int, reset:int}
     */
    private static function current(?array $existing, int $windowSeconds, int $now): array
    {
        if ($existing !== null && $existing['reset'] > $now) {
            return $existing;
        }

        return ['count' => 0, 'reset' => $now + $windowSeconds];
    }
}
