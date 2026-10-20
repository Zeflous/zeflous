<?php

declare(strict_types=1);

namespace Zef\Test\Support\Middleware;

/**
 * The Middleware namespace's hrtime double, owned by one support class
 * for the whole suite: the double itself lives in its own real file
 * ({@see hrtime_double.php}), declared in the middleware's own
 * namespace so PHP resolves the toolbar's unqualified hrtime calls to
 * it first.
 *
 * define() resets the typed state ({@see HrtimeDoubleState}) and makes
 * sure the one definition is loaded in this process, so each test that
 * queues ticks starts from the same empty cursor -- and a test that
 * never calls define() leaves the namespace unshadowed (the file is
 * only required here, never from the bootstrap).
 *
 * @internal
 */
final class HrtimeFunctionDoubles
{
    /**
     * Resets the double's state, optionally seeding the nanosecond tick
     * queue the double hands out in order before falling through to the
     * real engine hrtime.
     *
     * @param list<int> $ticks the nanosecond ticks to hand out in order
     */
    public static function define(array $ticks = []): void
    {
        HrtimeDoubleState::$ticks = $ticks;
        HrtimeDoubleState::$cursor = 0;

        require_once __DIR__ . '/hrtime_double.php';
    }
}
