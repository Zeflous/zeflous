<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Strict dependency supply-chain gate.
 *
 * Policy (fail-closed): any security advisory or abandoned package FAILS the
 * gate unless it is explicitly allow-listed in `composer.json` ->
 * `config.audit.ignore`, so an abandoned dependency can never slip in unnoticed.
 */
final readonly class DependencyAuditGate
{
    /**
     * @param list<string> $allowlist
     */
    public function __construct(private array $allowlist)
    {
    }

    public function evaluate(DependencyAudit $dependencyAudit): GateResult
    {
        $blockedAdvisories = $dependencyAudit->blockedAdvisories($this->allowlist);
        $blockedAbandoned = $dependencyAudit->blockedAbandoned($this->allowlist);

        if ($blockedAdvisories !== []) {
            return GateResult::failed(\sprintf(
                'Strict audit FAILED: %d package(s) with security advisories: %s',
                \count($blockedAdvisories),
                implode(', ', $blockedAdvisories),
            ));
        }

        if ($blockedAbandoned !== []) {
            return GateResult::failed(\sprintf(
                'Strict audit FAILED: %d abandoned package(s) outside the allow-list: %s',
                \count($blockedAbandoned),
                implode(', ', $blockedAbandoned),
            ));
        }

        $suffix = $this->allowlist === []
            ? ''
            : \sprintf(' Allow-listed (documented exceptions): %s.', implode(', ', $this->allowlist));

        return GateResult::passed(
            'Strict audit PASSED: 0 security advisories, 0 unallow-listed abandoned packages.' . $suffix,
        );
    }
}
