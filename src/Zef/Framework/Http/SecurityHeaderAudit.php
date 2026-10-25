<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The security-header audit itself: the answered record the walk fills
 * -- one finding per declared check, in declaration order -- and the
 * pass verdict over that record. The roadmap's debugging utilities
 * item, the offline spelling of the securityheaders.com scan.
 *
 * The audit answers against the response an application ACTUALLY
 * produced -- never the configuration that declares its posture --
 * so the verdict is the end-to-end truth: a middleware that failed
 * to run, a header a later middleware stripped, a value a host
 * overrode, all surface here exactly as the wire would carry them.
 * The per-check walk lives in {@see SecurityHeaderWalk}, the value
 * grammars in {@see SecurityHeaderGrammar}, and the declared checks
 * in {@see SecurityHeaderRuleset}.
 *
 * @internal
 */
final readonly class SecurityHeaderAudit
{
    /**
     * @param list<SecurityHeaderFinding> $findings the ruleset's verdicts, in
     *                                              declaration order
     */
    public function __construct(
        public array $findings,
    ) {
    }

    /**
     * Audits one answered response under the exchange's scheme.
     *
     * @param non-empty-string $scheme the exchange's scheme ('http' or 'https')
     */
    public static function over(ResponseInterface $response, string $scheme): self
    {
        $findings = [];

        foreach (SecurityHeaderRuleset::checks() as $securityHeaderCheck) {
            $findings[] = SecurityHeaderWalk::finding($securityHeaderCheck, $response, $scheme);
        }

        return new self($findings);
    }

    /**
     * Whether the audit passes: no actionable finding at the required
     * tier. Advisory findings are reported and never fail.
     */
    public function passed(): bool
    {
        return array_all($this->findings, $this->isPassing(...));
    }

    /**
     * Whether one finding keeps the audit passing: an actionable
     * finding at the advisory tier never fails, and a non-actionable
     * verdict never does either -- only the actionable required finding
     * is the failure.
     */
    private function isPassing(SecurityHeaderFinding $securityHeaderFinding): bool
    {
        return !$securityHeaderFinding->required || !$securityHeaderFinding->isFinding();
    }
}
