<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Enforces a minimum line-coverage percentage.
 *
 * The verdict compares the raw float -- never a rounded rendering of it
 * -- so a score of 89.9995 against a floor of 90 fails exactly as it
 * must. The failure message therefore carries the shortfall in
 * addition to the two `%.2f` figures: a verdict whose message reads
 * "90.00% (required: 90.00%)" and still fails is the honest boundary
 * case the raw comparison guards, and the shortfall is the one number
 * that explains it without making the operator guess at hidden
 * decimals.
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
            return GateResult::failed(\sprintf(
                '%s -> Coverage gate FAILED (short by %.4f%%).',
                $message,
                $this->threshold - $percentage,
            ));
        }

        return GateResult::passed($message . ' -> Coverage gate PASSED.');
    }
}
