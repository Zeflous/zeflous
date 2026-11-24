<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The Redis-backed replay seen-sight claim: the one INCR-atomic step
 * that decides whether a replay id's sight is its first, over the
 * phpredis command seam the rate-limit family's shared store already
 * rides ({@see RedisCommandsInterface}) -- the Redis composition of
 * the auth replay seam the HTTP Layer row's own open list names, the
 * cross-host fleet's spelling the interface's docblock promised as
 * a composition rather than a fork.
 *
 * The claim rides the same INCR-atomic doctrine the reference's own
 * remediation note spells for its counters ({@see
 * RedisSharedRateLimitStore}): no Lua script, no WATCH/MULTI/EXEC --
 * one server-side-atomic `INCR` is the whole accounting. A replayed
 * id's key starts its life at `INCR` answering `1`, so exactly one
 * request of the whole fleet ever answers the first sight; every
 * later sight inside the window answers a count above one and is a
 * duplicate by the same single step. The winner installs the
 * duplicate window with one `EXPIRE`, and the key's TTL carrying
 * the window means an id whose sight expired is fresh again exactly
 * as the in-memory table's cutoff and the APCu claim's TTL both make
 * theirs.
 *
 * The recovery arm covers the crash-between-INCR-and-EXPIRE state
 * the sister store answers for its buckets: a sight whose TTL reads
 * negative without being brand new has lost its window install, and
 * the window is re-installed whole -- the duplicate verdict stands
 * (the count is the accounting) while the key's life stays bounded,
 * so even a window whose first install was lost eventually elapses
 * and the id turns fresh again, never an immortal key a single lost
 * `EXPIRE` minted.
 *
 * `redisInt()` normalises the phpredis reply shapes exactly the way
 * the sister store's own fold does (int, or numeric string under
 * non-default reply options) and fails closed on anything else -- a
 * corrupt reply is the operator's outage, refused loudly with the
 * command named, never a count a duplicate verdict was guessed from.
 */
final readonly class RedisReplayStore
{
    public function __construct(
        private RedisCommandsInterface $redisCommands,
    ) {
    }

    /**
     * Claims one id's first sight: the atomic count that decides,
     * with the duplicate window installed on the winner's step.
     *
     * @param string $key           the sight's storage key, the protector's
     *                              own spelling
     * @param int    $windowSeconds the duplicate window's length, in
     *                              seconds (>= 1) -- the TTL the winner
     *                              installs, so the sight's life IS the
     *                              window
     *
     * @return bool true when this call won the id's first sight, false
     *              when the id was already sighted inside its window
     *
     * @throws HttpException when a reply is corrupt -- the fail-closed
     *                       refusal with the command named, never a
     *                       verdict guessed from an unreadable count
     */
    public function claim(string $key, int $windowSeconds): bool
    {
        // One atomic write per sight: N requests of the fleet
        // incrementing the same key count exactly N between them, so
        // exactly one of them answers the first sight.
        $count = $this->redisInt($this->redisCommands->incr($key), 'INCR');

        $ttl = $this->redisInt($this->redisCommands->ttl($key), 'TTL');

        if ($count === 1 || $ttl < 0) {
            // The winner installs the duplicate window; a sight that
            // lost its TTL to a crash between INCR and EXPIRE is
            // re-installed whole -- the recovery arm the sister
            // store's own crash state answers with.
            $this->redisCommands->expire($key, $windowSeconds);
        }

        return $count === 1;
    }

    /**
     * Normalises one phpredis reply into an integer: the int itself, or
     * the numeric string some reply options carry. Anything else is a
     * corrupt reply and fails closed -- the store never answers a
     * count it cannot vouch for.
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
            'Redis replay store: ' . $command . ' returned an unexpected value.',
        );
    }
}
