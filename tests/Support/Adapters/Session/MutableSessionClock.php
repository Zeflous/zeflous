<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Session;

/**
 * The file session adapter's advancing test clock: the frozen-clock
 * discipline the in-memory driver's injectable closure spells,
 * grown the one arm the cadence tests need -- a clock the test
 * ADVANCES mid-flight, so one adapter instance (one sweep state)
 * can be asked across a cadence boundary without the suite
 * re-creating the adapter and losing the state the boundary is
 * there to exercise.
 *
 * The analyser-friendly spelling is the point: a by-reference
 * closure over a local variable is a read the dead-code and
 * unused-variable sniffs cannot see, while a named support class
 * with its own verbs is one both read honestly.
 *
 * @internal
 */
final class MutableSessionClock
{
    public function __construct(
        private int $at,
    ) {
    }

    /**
     * Advances the clock to the asked reading.
     */
    public function advanceTo(int $at): void
    {
        $this->at = $at;
    }

    /**
     * The clock's current reading: the closure the adapter reads.
     */
    public function now(): int
    {
        return $this->at;
    }
}
