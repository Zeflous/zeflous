<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Outcome of a persistent-worker smoke run: how many iterations actually ran and
 * how much memory the worker grew by.
 */
final readonly class SmokeResult
{
    public function __construct(
        public int $iterations,
        public int $memoryGrowth,
    ) {
    }
}
