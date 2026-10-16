<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Closure;

/**
 * Stepping clock double: the injectable `Closure(): int` clock seam
 * whose every read advances the answer by a fixed step -- the shape
 * the timing family's two-reading span needs (one read at each edge
 * of the measured pipeline), so a test freezes the elapsed span to
 * an exact value without a by-reference closure capture (the coding
 * standard's unused-variable analysis cannot see those reads, the
 * same reason {@see MutableClock} exists).
 *
 * A negative step is a legal double: it simulates the broken clock
 * seam whose second reading lands before its first -- the artifact
 * the elapsed clamp exists to absorb.
 */
final class SteppingClock
{
    private int $reads = 0;

    public function __construct(
        public int $epoch = 0,
        public int $step = 0,
    ) {
    }

    /**
     * The clock closure the timing middleware's constructor accepts:
     * the epoch on the first read, the epoch plus one step on the
     * second, and so on.
     *
     * @return Closure():int
     */
    public function now(): Closure
    {
        return fn (): int => $this->epoch + $this->step * $this->reads++;
    }

    /**
     * The reads the clock has answered so far -- the count a test
     * pins when it asserts a path never measured.
     */
    public function readCount(): int
    {
        return $this->reads;
    }
}
