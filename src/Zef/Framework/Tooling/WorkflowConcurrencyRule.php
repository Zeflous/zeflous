<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The pure inspection rule behind {@see WorkflowConcurrencyGate}: given a
 * workflow file's name and raw YAML, it returns the supersede-policy violation
 * message, or null when the workflow honours the policy (or is out of scope).
 *
 * This class only decides whether the workflow is a POLLING workflow at all;
 * the YAML-level decision lives in {@see WorkflowConcurrencyPolicy}. Keeping the
 * two apart keeps each unit small and reviewable.
 */
final readonly class WorkflowConcurrencyRule
{
    public function violation(string $name, string $raw): ?string
    {
        if (!str_contains($raw, 'check-runs')) {
            // Not a polling workflow: it never holds a concurrency group open
            // waiting on another workflow's checks, so it is out of scope.
            return null;
        }

        return new WorkflowConcurrencyPolicy()->violation($name, $raw);
    }
}
