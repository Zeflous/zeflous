<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Regression gate for the GitHub Actions concurrency configuration.
 *
 * It guards the two defects that made the required `CI Strict` check sit in
 * "waiting" and blocked auto-merge:
 *
 *  1. WORD-SPLITTING. A bash array whose elements contain spaces (the strict
 *     lane names) must be expanded as `"${name[@]}"`. A bare `${name}` expands
 *     only the first element and then word-splits it, so every lane lookup
 *     resolved to a name that does not exist and the poll spun until its
 *     deadline. This gate fails on any bare expansion of a declared array.
 *
 *  2. NON-COMMIT-KEYED CONCURRENCY. The `CI Strict` aggregator must key its
 *     concurrency group on the COMMIT under verification, not on the branch.
 *     A per-branch group lets a new run queue behind a stale one, so the new
 *     run is created `pending` with 0 jobs and its required check-run is never
 *     created -- the "waiting" deadlock. This gate fails when the aggregator's
 *     group is not commit-keyed.
 *
 * The gate is pure: it reads a {@see WorkflowConcurrencyReport} and returns a
 * {@see GateResult}, so it is fully unit-testable.
 */
final readonly class WorkflowConcurrencyGate
{
    /**
     * The aggregator workflow that preserves the required `CI Strict` context.
     */
    private const string AGGREGATOR = 'ci-strict.yml';

    public function evaluate(WorkflowConcurrencyReport $workflowConcurrencyReport): GateResult
    {
        $failures = [];

        foreach ($workflowConcurrencyReport->workflows as $name => $contents) {
            foreach ($this->inspect($name, $contents) as $failure) {
                $failures[] = $failure;
            }
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

    /**
     * @return list<string>
     */
    private function inspect(string $name, string $contents): array
    {
        $failures = [];

        if ($this->hasBareArrayExpansion($contents)) {
            $failures[] = \sprintf(
                '%s expands a bash array without [@] (word-splitting).',
                $name,
            );
        }

        if ($name === self::AGGREGATOR) {
            $group = $this->concurrencyGroup($contents);

            if (!$this->isCommitKeyed($group)) {
                $failures[] = \sprintf(
                    '%s must key its concurrency group on the commit sha, not the branch (found: %s).',
                    $name,
                    $group ?? 'none',
                );
            }
        }

        return $failures;
    }

    /**
     * True when a variable declared as a bash array (`name=(...)`) is expanded
     * bare (`${name}`) instead of as `"${name[@]}"`.
     */
    private function hasBareArrayExpansion(string $contents): bool
    {
        $code = $this->stripComments($contents);
        $arrays = [];
        $bare = [];
        preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)=\(/', $code, $arrays);
        preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}(?!\[)/', $code, $bare);

        return array_intersect($arrays[1], $bare[1]) !== [];
    }

    /**
     * Removes full-line comments so a comment that merely MENTIONS a bare
     * expansion (e.g. the explanatory note in ci-strict.yml) is not mistaken
     * for the defect itself.
     */
    private function stripComments(string $contents): string
    {
        return preg_replace('/^[ \t]*#.*$/m', '', $contents) ?? '';
    }

    /**
     * The value of the first `group:` key, or null when the workflow declares
     * no concurrency group.
     */
    private function concurrencyGroup(string $contents): ?string
    {
        if (preg_match('/\s*group:\s*(.+?)\s*$/m', $contents, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * A group is commit-keyed when it references the commit sha (either the
     * push sha or the pull-request head sha) rather than only the branch ref.
     */
    private function isCommitKeyed(?string $group): bool
    {
        if ($group === null) {
            return false;
        }

        return str_contains($group, 'github.sha')
            || str_contains($group, 'github.event.pull_request.head.sha');
    }
}
