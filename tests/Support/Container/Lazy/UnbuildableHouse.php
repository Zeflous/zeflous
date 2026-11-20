<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

use RuntimeException;

/**
 * Fixture service whose construction always fails but whose members a
 * first use can touch: the lazy lane's own spelling for the deferral's
 * honest failure posture -- the warm boot stays green (the warmer
 * answers the proxy, the factory never run), and the first use runs
 * the factory, which throws (issue #800's finding 2, the recorded
 * interaction). The entered member reads the instance's own state, the
 * one access PHP's native lazy proxy cannot answer from the shell
 * alone.
 */
final class UnbuildableHouse
{
    public function __construct(
        public string $door = 'left',
    ) {
        throw new RuntimeException('the unbuildable house cannot be built');
    }

    public function enter(): string
    {
        return $this->door;
    }
}
