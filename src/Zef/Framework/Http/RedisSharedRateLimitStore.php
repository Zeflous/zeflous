<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;

/**
 * Redis-backed shared rate-limit store ({@see SharedRateLimitStoreInterface}).
 *
 * Fixed-window counters are kept atomic across all workers and hosts using
 * only native commands: one server-side-atomic `INCR` is the single write
 * per bucket, the key's TTL carries the window, and the reset epoch is
 * derived from that TTL.
 *
 * Window semantics (the reference's, preserved): a bucket starts on its
 * first request -- `INCR` answering `1` installs the full-window TTL --
 * and afterwards resets at `now + remaining TTL`. The recovery arm covers
 * the crash-between-INCR-and-EXPIRE state (a key whose TTL reads negative
 * without being brand new): the window TTL is re-installed whole. The
 * reference's diagnostic `peek()` read is deliberately not ported: no
 * production caller exists for it, and this repository carries no
 * promises nothing consumes.
 *
 * `redisInt()` normalises the phpredis reply shapes (int, or numeric
 * string under non-default reply options) and fails closed on anything
 * else -- a corrupt reply is an error, not a count the caller throttles
 * with.
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * RedisSharedRateLimitStore.php` (move-verbatim INCR+EXPIRE semantics and
 * the redisInt normalisation; restructured dependency: the `\Redis`
 * member becomes the {@see RedisCommandsInterface} seam so the semantics
 * stay pure and testable against a double).
 */
final readonly class RedisSharedRateLimitStore implements SharedRateLimitStoreInterface
{
    private const string KEY_PREFIX = 'zef:ratelimit:';

    public function __construct(
        private RedisCommandsInterface $redisCommands,
    ) {
    }

    #[Override]
    public function increment(string $key, int $windowSeconds, int $now): array
    {
        $bucketKey = self::KEY_PREFIX . hash('sha256', $key);

        // One atomic write per bucket: N workers incrementing the same key
        // count exactly N between them, cross-host included.
        $count = $this->redisInt($this->redisCommands->incr($bucketKey), 'INCR');

        $ttl = $this->redisInt($this->redisCommands->ttl($bucketKey), 'TTL');

        if ($count === 1 || $ttl < 0) {
            // A brand-new bucket installs the full window; a key that lost
            // its TTL to a crash between INCR and EXPIRE is re-installed.
            $this->redisCommands->expire($bucketKey, $windowSeconds);

            return ['count' => $count, 'reset' => $now + $windowSeconds];
        }

        // An existing bucket resets when its TTL runs out.
        return ['count' => $count, 'reset' => $now + $ttl];
    }

    /**
     * Normalises one phpredis reply into an integer: the int itself, or
     * the numeric string some reply options carry. Anything else is a
     * corrupt reply and fails closed -- the store never answers a count
     * it cannot vouch for.
     */
    private function redisInt(mixed $value, string $command): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new HttpException(
            'Redis rate-limit store: ' . $command . ' returned an unexpected value.',
        );
    }
}
