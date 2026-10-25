<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The security-header audit's walk: one check's verdict over one
 * answered response -- the fold the {@see SecurityHeaderAudit}
 * delegates per declared check, split out of the audit the way the
 * lane's other families split their own folds (the difficulty
 * ceiling's own doctrine: the record, the walk that fills it, and the
 * grammar the walk judges with are three units, not one).
 *
 * The walk owns the audit's one applicability rule, mirroring the
 * toggle family's own: an https-only check over a cleartext exchange
 * answers not-applicable, because a browser never persists an HSTS
 * header from cleartext -- the same reason {@see
 * ToggledSecurityHeaders} refuses to emit one there. A repeated field
 * audits through its joined header line, so a duplicated hardening
 * header fails the value grammar honestly: the duplication itself is
 * the defect the joined spelling exposes.
 *
 * @internal
 */
final class SecurityHeaderWalk
{
    /**
     * One check's verdict over the response.
     *
     * @param non-empty-string $scheme the exchange's scheme
     */
    public static function finding(
        SecurityHeaderCheck $securityHeaderCheck,
        ResponseInterface $response,
        string $scheme,
    ): SecurityHeaderFinding {
        if ($securityHeaderCheck->httpsOnly && $scheme === 'http') {
            return SecurityHeaderFinding::notApplicable($securityHeaderCheck);
        }

        if ($response->getHeader($securityHeaderCheck->name) === []) {
            return SecurityHeaderFinding::missing($securityHeaderCheck);
        }

        $value = $response->getHeaderLine($securityHeaderCheck->name);

        return SecurityHeaderGrammar::accepts($securityHeaderCheck, $value)
            ? SecurityHeaderFinding::pass($securityHeaderCheck, $value)
            : SecurityHeaderFinding::invalid($securityHeaderCheck, $value);
    }
}
