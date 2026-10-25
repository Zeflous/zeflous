<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Http\SecurityHeaderAudit;
use Zef\Framework\Http\SecurityHeaderFinding;

/**
 * The header validator command's JSON form: the tool-integration view
 * the audit answers with `--json` -- the verdict, the per-verdict
 * counts, and one record per declared check in declaration order --
 * over the route inspection family's one encoding spelling ({@see
 * RouteInspectionJson::encode}).
 */
final readonly class HeaderAuditJson
{
    /**
     * The whole audit as a JSON document.
     */
    public static function rendered(SecurityHeaderAudit $securityHeaderAudit): string
    {
        $counts = [
            SecurityHeaderFinding::VERDICT_PASS => 0,
            SecurityHeaderFinding::VERDICT_INVALID => 0,
            SecurityHeaderFinding::VERDICT_MISSING => 0,
            SecurityHeaderFinding::VERDICT_NOT_APPLICABLE => 0,
        ];

        $records = [];

        foreach ($securityHeaderAudit->findings as $finding) {
            ++$counts[$finding->verdict];

            $records[] = [
                'header' => $finding->header,
                'verdict' => $finding->verdict,
                'required' => $finding->required,
                'expected' => $finding->expected,
                'actual' => $finding->actual,
            ];
        }

        return RouteInspectionJson::encode([
            'passed' => $securityHeaderAudit->passed(),
            'counts' => $counts,
            'findings' => $records,
        ]);
    }
}
