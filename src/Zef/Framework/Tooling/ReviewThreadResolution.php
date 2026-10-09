<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Outcome of a {@see ReviewThreadResolver} run: how many conversations the pull
 * request carried, how many finished ones were resolved, and how many resolve
 * attempts failed.
 *
 * The lane is fail-soft: a failed resolve is reported (and retried on the next
 * sweep) but never aborts the sweep, because one stubborn thread must not stop
 * the other pull requests from being processed.
 */
final readonly class ReviewThreadResolution
{
    public function __construct(
        public int $total,
        public int $resolved,
        public int $failed,
    ) {
    }

    public function message(): string
    {
        return \sprintf(
            'Review threads: %d total, %d finished conversation(s) resolved, %d failed.',
            $this->total,
            $this->resolved,
            $this->failed,
        );
    }
}
