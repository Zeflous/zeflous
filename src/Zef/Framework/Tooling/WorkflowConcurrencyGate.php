<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Enforces the repository's workflow-concurrency policy for POLLING workflows.
 *
 * WHY THIS GATE EXISTS
 * --------------------
 * The `CI Strict` aggregator polls the four strict lanes for up to 60 minutes.
 * While it QUEUED (`cancel-in-progress: false`) on a REF-SCOPED concurrency
 * group, a superseded run held that group open for the whole window, so the run
 * for the newest head sat `pending` with 0 jobs and its required `CI Strict`
 * check-run was never created -- the pull request stayed `blocked` with every
 * lane green, and GitHub's native auto-merge (correctly armed) had nothing to
 * merge on. This gate makes that exact regression impossible to reintroduce
 * silently.
 *
 * THE INVARIANT
 * -------------
 * A workflow that polls another workflow's check-runs (`check-runs` API call)
 * and scopes its concurrency group to a ref (`github.ref`) MUST supersede a
 * stale run of its own event type (`concurrency.cancel-in-progress: true`).
 * A ref-scoped group is what lets a stale run block the newest head's required
 * check; superseding removes the block.
 *
 * WHAT IS DELIBERATELY OUT OF SCOPE
 * ---------------------------------
 *   * A polling workflow with NO concurrency block: its runs never queue
 *     against each other, so no stale run can block a newer one.
 *   * A polling workflow whose group is NOT ref-scoped (e.g. the global
 *     `auto-merge` FIFO group): a stale run cannot block a specific ref's
 *     required check, so queueing there is a legitimate design choice.
 *   * A non-polling workflow: it never holds a group open waiting on another
 *     workflow's checks.
 *
 * The per-file decision lives in {@see WorkflowConcurrencyRule}; this class only
 * walks the workflow directory and aggregates the verdict. It is fail-closed on
 * a polling workflow whose YAML cannot be parsed or is not a mapping.
 */
final readonly class WorkflowConcurrencyGate
{
    public function __construct(private string $workflowsDirectory)
    {
    }

    public function evaluate(): GateResult
    {
        $violations = [];

        foreach ($this->workflowFiles() as $file) {
            $raw = (string) file_get_contents($file);

            if (!str_contains($raw, 'check-runs')) {
                // Not a polling workflow -> out of scope.
                continue;
            }

            $violation = new WorkflowConcurrencyRule()->violation(basename($file), $raw);

            if ($violation === null) {
                continue;
            }

            $violations[] = $violation;
        }

        if ($violations === []) {
            return GateResult::passed('Workflow concurrency: all polling workflows honour the supersede policy.');
        }

        return GateResult::failed(\sprintf(
            'Workflow concurrency: %d polling workflow(s) violate the supersede policy -> %s',
            \count($violations),
            implode('; ', $violations),
        ));
    }

    /**
     * @return list<string>
     */
    private function workflowFiles(): array
    {
        $files = glob($this->workflowsDirectory . '/*.{yml,yaml}', \GLOB_BRACE);

        return \is_array($files) ? $files : [];
    }
}
