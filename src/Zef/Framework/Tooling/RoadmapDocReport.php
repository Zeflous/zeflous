<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The roadmap / delivery documents that the documentation-integrity gate
 * audits.
 *
 * Pure data: the map of document name => contents. Building the map is the job
 * of {@see RoadmapDocFileCollector}, so this report can be handed to the pure
 * {@see RoadmapDocGate} deterministically in tests.
 */
final readonly class RoadmapDocReport
{
    /**
     * @param array<string, string> $documents map of file name => file contents
     */
    public function __construct(public array $documents)
    {
    }

    public function count(): int
    {
        return \count($this->documents);
    }
}
