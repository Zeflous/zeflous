<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The GitHub Actions workflow files that the concurrency gate audits.
 *
 * Pure data: the map of workflow file name => file contents. Building the map
 * is the job of {@see WorkflowFileCollector}, so this report can be handed to
 * the pure {@see WorkflowConcurrencyGate} deterministically in tests.
 */
final readonly class WorkflowConcurrencyReport
{
    /**
     * @param array<string, string> $workflows map of file name => file contents
     */
    public function __construct(public array $workflows)
    {
    }

    public function count(): int
    {
        return \count($this->workflows);
    }
}
