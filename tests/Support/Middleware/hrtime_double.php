<?php

/**
 * The toolbar's hrtime double, loaded once into the middleware's own
 * namespace for the whole suite: DebugToolbarMiddleware calls hrtime
 * unqualified, so PHP resolves the call in that namespace first -- this
 * double shadows the engine call wherever the suite runs.
 *
 * This is a real, analysed file rather than an eval'd string (the same
 * contract the APCu boundary's doubles carry): the analysers read the
 * exact signature the middleware exercises, the return types mirror the
 * engine's own (the boolean form answers the [seconds, nanoseconds]
 * pair, the numeric form the tick), and nothing needs a dynamic
 * definition. The state it answers from is the typed support class
 * ({@see \Zef\Test\Support\Middleware\HrtimeDoubleState}); the support
 * class that owns the resets ({@see \Zef\Test\Support\Middleware\HrtimeFunctionDoubles})
 * requires this file once per process.
 *
 * The doubles queue nanosecond ticks and fall through to the real
 * engine hrtime once the queue is exhausted, so a test that queues
 * [t0, t1] pins the middleware's measured wall clock exactly -- the
 * two ticks are the started-at/now pair -- while every other caller
 * keeps real time.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Test\Support\Middleware\HrtimeDoubleState;

/**
 * @phpstan-return ($asNumber is true ? int : array{int, int})
 *
 * @psalm-return ($asNumber is true ? int : array{int, int})
 */
function hrtime(bool $asNumber = false): array|int
{
    $tick = HrtimeDoubleState::nextTick();

    if ($asNumber) {
        return $tick;
    }

    return [intdiv($tick, 1_000_000_000), $tick % 1_000_000_000];
}
