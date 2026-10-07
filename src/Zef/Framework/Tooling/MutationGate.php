<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Enforces a floor on both the mutation score index (MSI) and the covered-code
 * MSI. Fail-closed: a missing report is an error, never a silent pass.
 */
final readonly class MutationGate
{
    public function __construct(private float $floor)
    {
    }

    public function evaluate(MutationReport $mutationReport): GateResult
    {
        $message = \sprintf(
            'Mutation score: MSI %.2f%% / covered MSI %.2f%% (code coverage %.2f%%, floor: %.2f%%)',
            $mutationReport->msi,
            $mutationReport->coveredMsi,
            $mutationReport->codeCoverage,
            $this->floor,
        );

        if ($mutationReport->msi < $this->floor || $mutationReport->coveredMsi < $this->floor) {
            return GateResult::failed($message . ' -> Mutation-score floor FAILED.');
        }

        return GateResult::passed($message . ' -> Mutation-score floor PASSED.');
    }
}
