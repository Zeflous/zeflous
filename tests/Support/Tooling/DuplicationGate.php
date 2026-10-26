<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The copy-paste gate: any clone pair the detector found in `src/` is a
 * failure that names both spans, because a duplication that ships is a
 * duplication the next reader pays for. The vendored PSR shim
 * (`src/Contracts/Psr/`) stays outside the detector's scope -- it is
 * third-party boilerplate this repository mirrors, not code it owns,
 * the same boundary `sonar.cpd.exclusions` already draws.
 */
final readonly class DuplicationGate
{
    public function evaluate(DuplicationReport $duplicationReport): GateResult
    {
        if ($duplicationReport->clones === []) {
            return GateResult::passed(
                'Duplication gate PASSED: the copy-paste detector found no clone in src/.',
            );
        }

        $rendered = [];

        foreach ($duplicationReport->clones as $clone) {
            $spans = [];

            foreach ($clone['files'] as $file) {
                $spans[] = \sprintf('%s:%d-%d', $file['path'], $file['line'], $file['endLine']);
            }

            $rendered[] = \sprintf('%s (%d lines)', implode(' <-> ', $spans), $clone['lines']);
        }

        return GateResult::failed(
            'Duplication gate FAILED: ' . \count($rendered) . ' code clone(s) in src/: '
            . implode('; ', $rendered) . '.',
        );
    }
}
