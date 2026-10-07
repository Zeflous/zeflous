<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Enforces a minimum line-coverage percentage.
 */
final readonly class CoverageGate
{
    public function __construct(private float $threshold)
    {
    }

    public function evaluate(CoverageReport $coverageReport): GateResult
    {
        $percentage = $coverageReport->percentage();

        $message = \sprintf(
            'Line coverage: %.2f%% (required: %.2f%%)',
            $percentage,
            $this->threshold,
        );

        if ($percentage < $this->threshold) {
            return GateResult::failed($message . ' -> Coverage gate FAILED.');
        }

        return GateResult::passed($message . ' -> Coverage gate PASSED.');
    }
}
