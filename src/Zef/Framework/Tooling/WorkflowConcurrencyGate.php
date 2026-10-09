<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Regression gate for the GitHub Actions concurrency configuration.
 *
 * It guards the defects that made the required `CI Strict` check sit in
 * "waiting" and blocked auto-merge:
 *
 *  1. WORD-SPLITTING: a bash array whose elements contain spaces (the strict
 *     lane names) must be expanded as `"${name[@]}"` -- a bare `${name}`
 *     makes every lane lookup resolve to a name that does not exist and the
 *     poll spins until its deadline. Detected by
 *     {@see BashArrayExpansionDetector}.
 *
 *  2. NON-COMMIT-KEYED CONCURRENCY: a cancelling workflow whose group is not
 *     keyed on the commit under verification lets a stale run cancel the
 *     newest head's run, and the aggregator itself must always be
 *     commit-keyed regardless of cancellation. Both rules live in
 *     {@see WorkflowConcurrencyPolicy}.
 *
 * The gate is pure: it evaluates a {@see WorkflowConcurrencyReport} and
 * returns a {@see GateResult}, so it is fully unit-testable.
 */
final readonly class WorkflowConcurrencyGate
{
    public function evaluate(WorkflowConcurrencyReport $workflowConcurrencyReport): GateResult
    {
        $failures = [];

        foreach ($workflowConcurrencyReport->workflows as $workflow => $contents) {
            $failures = [
                ...$failures,
                ...BashArrayExpansionDetector::failures($workflow, $contents),
                ...WorkflowConcurrencyPolicy::failures($workflow, $contents),
            ];
        }

        if ($failures !== []) {
            return GateResult::failed(\sprintf(
                'Workflow concurrency gate FAILED: %s',
                implode(' ', $failures),
            ));
        }

        return GateResult::passed(\sprintf(
            'Workflow concurrency gate PASSED: %d workflow(s) audited.',
            $workflowConcurrencyReport->count(),
        ));
    }
}
