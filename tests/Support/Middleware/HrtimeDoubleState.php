<?php

declare(strict_types=1);

namespace Zef\Test\Support\Middleware;

/**
 * The Middleware namespace's hrtime double state, typed: the queued
 * nanosecond ticks the double ({@see hrtime_double.php}) hands out in
 * order -- a short-write schedule for TIME instead of bytes -- plus the
 * cursor that consumes them, so one process can replay a deterministic
 * clock across the middleware's started-at/now pair.
 *
 * Once the queue is exhausted the double falls through to the real
 * engine hrtime, which keeps every other suite's timing behaviour
 * untouched even though the namespaced shadow is loaded for the whole
 * process (the middleware namespace's only hrtime callers are the
 * toolbar's two calls, both under the tests that queue ticks).
 *
 * @internal
 */
final class HrtimeDoubleState
{
    /** @var list<int> */
    public static array $ticks = [];

    public static int $cursor = 0;

    public static function nextTick(): int
    {
        if (self::$cursor < \count(self::$ticks)) {
            $tick = self::$ticks[self::$cursor];
            ++self::$cursor;

            return $tick;
        }

        return (int) hrtime(true);
    }

    public static function reset(): void
    {
        self::$ticks = [];
        self::$cursor = 0;
    }
}
