<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Decides whether a polling workflow's concurrency policy violates the
 * supersede rule: a workflow that polls another workflow's check-runs and
 * scopes its concurrency group to a ref (`github.ref`) must set
 * `concurrency.cancel-in-progress: true`.
 *
 * Split out of {@see WorkflowConcurrencyRule} so each unit stays small enough
 * for the architecture fitness gate (Halstead difficulty) and the return-count
 * rule.
 */
final readonly class WorkflowConcurrencyPolicy
{
    public function violation(string $name, string $raw): ?string
    {
        try {
            $document = Yaml::parse($raw);
        } catch (ParseException) {
            return \sprintf('%s: unparseable YAML', $name);
        }

        if (!\is_array($document)) {
            return \sprintf('%s: not a mapping', $name);
        }

        return $this->queuesOnRef($name, $document);
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private function queuesOnRef(string $name, array $document): ?string
    {
        $concurrency = MixedValue::arrayOrNull($document['concurrency'] ?? null);
        $group = $concurrency === null ? null : MixedValue::stringOrNull($concurrency['group'] ?? null);

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
}
