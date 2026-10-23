<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * PHPStan "no new debt" ratchet.
 *
 * The framework must start from a clean baseline: a committed
 * `phpstan-baseline.neon` that ignores errors is not accepted, because it hides
 * regressions. This gate fails whenever a non-empty baseline is committed.
 *
 * The ignored-error count reads every spelling PHPStan emits: the block
 * `message:` key on its own line (with or without a leading list dash),
 * the flow-mapping shape `- {message: '...', count: 1, path: '...'}`, the
 * identifier-only entries PHPStan accepts as the documented alternative
 * (`identifier: 'class.notFound'`, block or flow), and the mid-flow
 * `, message:` continuation a compact flow mapping carries. A count that
 * over-matches (a message pattern that itself contains `, message:`)
 * fails the gate in the fail-closed direction -- the operator who meets
 * it rewrites the baseline entry, never the ratchet.
 */
final readonly class BaselineGate
{
    /**
     * Every key position an ignored error can occupy: at the start of a
     * line (block or list-item), directly after a flow mapping's opening
     * brace, or after the comma that separates two flow keys.
     */
    private const string IGNORED_KEY = '/(?:^|[,{}])[ \t]*(?:-[ \t]*)?(?:message|identifier)[ \t]*:/m';

    public function evaluate(?string $baselineContents): GateResult
    {
        if ($baselineContents === null) {
            return GateResult::passed(
                'PHPStan ratchet PASSED: no baseline file present (zero accepted errors).',
            );
        }

        $ignoredErrors = preg_match_all(self::IGNORED_KEY, $baselineContents);

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
