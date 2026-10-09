<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The pure inspection rule behind {@see WorkflowConcurrencyGate}: given a
 * workflow file's name and raw YAML, it returns the supersede-policy violation
 * message, or null when the workflow honours the policy (or is out of scope).
 *
 * Kept as its own class so the gate stays a thin orchestrator and each unit
 * carries a small, reviewable amount of logic.
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

        try {
            $document = Yaml::parse($raw);
        } catch (ParseException) {
            return \sprintf('%s: unparseable YAML', $name);
        }

        if (!\is_array($document)) {
            return \sprintf('%s: not a mapping', $name);
        }

        $concurrency = $this->arrayOrNull($document['concurrency'] ?? null);
        $group = $concurrency === null ? null : $this->stringOrNull($concurrency['group'] ?? null);

        if ($group === null || !str_contains($group, 'github.ref')) {
            // No concurrency block, a bare scalar group, or a non-ref-scoped
            // group: a stale run cannot block a specific ref's required check,
            // so there is nothing to supersede.
            return null;
        }

        if (($concurrency['cancel-in-progress'] ?? null) === true) {
            return null;
        }

        return \sprintf(
            '%s: polls check-runs on a ref-scoped group without cancel-in-progress: true',
            $name,
        );
    }

    /**
     * @return null|array<array-key, mixed>
     */
    private function arrayOrNull(mixed $value): ?array
    {
        return \is_array($value) ? $value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
