<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The mess gate: any violation under the opted-in ruleset
 * (`phpmd.xml`, per-rule opt-in) is a failure that groups the findings
 * by rule with every location named, so a red gate answers "which rule,
 * where" without a re-run. Rules join the ruleset deliberately -- the
 * census and the recipe live in `docs/ci-notes/qa-gates.md`.
 */
final readonly class MessGate
{
    public function evaluate(MessReport $messReport): GateResult
    {
        if ($messReport->violations === []) {
            return GateResult::passed(
                'Mess gate PASSED: no violation under the opted-in ruleset (phpmd.xml).',
            );
        }

        $byRule = [];

        foreach ($messReport->violations as $violation) {
            $byRule[$violation['rule']][] = $violation['file'] . ':' . $violation['line'];
        }

        ksort($byRule);

        $rendered = [];

        foreach ($byRule as $rule => $locations) {
            $rendered[] = \sprintf('%s x%d (%s)', $rule, \count($locations), implode(', ', $locations));
        }

        return GateResult::failed(
            'Mess gate FAILED: ' . \count($messReport->violations) . ' violation(s) under the opted-in ruleset: '
            . implode('; ', $rendered) . '.',
        );
    }
}
