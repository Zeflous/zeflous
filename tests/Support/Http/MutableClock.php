<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Closure;

/**
 * Mutable clock double: the injectable `Closure(): int` clock seam with a
 * settable epoch, so a test moves time forward between checks without a
 * by-reference closure capture (the coding standard's unused-variable
 * analysis cannot see those reads).
 */
final class MutableClock
{
    public function __construct(
        public int $epoch = 0,
    ) {
    }

    /**
     * The clock closure the limiter's constructor accepts.
     *
     * @return Closure():int
     */
    public function now(): Closure
    {
        return fn (): int => $this->epoch;
    }

    /**
     * Moves the clock forward by the given seconds.
     */
    public function advance(int $seconds): void
    {
        $this->epoch += $seconds;
    }
}
