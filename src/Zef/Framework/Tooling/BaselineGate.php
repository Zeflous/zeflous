<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * PHPStan "no new debt" ratchet.
 *
 * The framework must start from a clean baseline: a committed
 * `phpstan-baseline.neon` that ignores errors is not accepted, because it hides
 * regressions. This gate fails whenever a non-empty baseline is committed.
 */
final readonly class BaselineGate
{
    public function evaluate(?string $baselineContents): GateResult
    {
        if ($baselineContents === null) {
            return GateResult::passed(
                'PHPStan ratchet PASSED: no baseline file present (zero accepted errors).',
            );
        }

        // Matches both YAML shapes PHPStan emits: a `message:` key on its own
        // line, and the inline `- message:` list-item form.
        $ignoredErrors = preg_match_all('/^\s*(?:-\s*)?message\s*:/m', $baselineContents);

        if ($ignoredErrors === false) {
            throw new ToolingException('Unable to evaluate the PHPStan baseline file.');
        }

        if ($ignoredErrors > 0) {
            return GateResult::failed(\sprintf(
                'PHPStan ratchet FAILED: phpstan-baseline.neon ignores %d error(s); fix them instead of baselining.',
                $ignoredErrors,
            ));
        }

        return GateResult::passed('PHPStan ratchet PASSED: baseline is empty.');
    }
}
