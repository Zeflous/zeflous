<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The radix tree's build counters: the identifier count, the raw
 * segment total, and the deepest path -- the measurements the seal
 * hands the product, split from {@see NamespaceTreeBuilder} as their
 * own value object so the builder's insert walk stays a walk and the
 * arithmetic stays a fold.
 */
final readonly class NamespaceTreeCounters
{
    public function __construct(
        private int $serviceCount = 0,
        private int $rawSegments = 0,
        private int $maxDepth = 0,
    ) {
    }

    /**
     * The counters with one more identifier recorded: the count up by
     * one, the raw segments up by the path's length, the depth kept at
     * the deepest path seen.
     *
     * @param list<string> $segments the inserted identifier's segments
     */
    public function recorded(array $segments): self
    {
        return new self(
            $this->serviceCount + 1,
            $this->rawSegments + \count($segments),
            max($this->maxDepth, \count($segments)),
        );
    }

    /**
     * How many identifiers the build indexed.
     */
    public function serviceCount(): int
    {
        return $this->serviceCount;
    }

    /**
     * The raw segment total the build walked.
     */
    public function rawSegments(): int
    {
        return $this->rawSegments;
    }

    /**
     * The deepest path the build indexed.
     */
    public function maxDepth(): int
    {
        return $this->maxDepth;
    }
}
