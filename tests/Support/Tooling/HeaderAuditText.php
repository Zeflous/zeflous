<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Http\SecurityHeaderAudit;
use Zef\Framework\Http\SecurityHeaderFinding;

/**
 * The header validator command's text form: the audit's table -- one
 * line per declared check (the header field, its verdict, the
 * expected-versus-actual detail), closed by the audit's own verdict
 * line with the count of actionable required findings when it fails.
 */
final readonly class HeaderAuditText
{
    /**
     * The whole audit as one table.
     */
    public static function table(SecurityHeaderAudit $securityHeaderAudit): string
    {
        $lines = [];

        foreach ($securityHeaderAudit->findings as $finding) {
            $lines[] = mb_str_pad($finding->header, 34)
                . mb_str_pad($finding->verdict, 17)
                . self::detail($finding);
        }

        $lines[] = '';
        $lines[] = $securityHeaderAudit->passed()
            ? 'Security header audit: PASS'
            : 'Security header audit: FAIL ('
                . self::requiredFindingCount($securityHeaderAudit)
                . ' required finding(s))';

        return implode("\n", $lines) . "\n";
    }

    /**
     * One finding's expected-versus-actual detail.
     */
    private static function detail(SecurityHeaderFinding $securityHeaderFinding): string
    {
        return match (true) {
            $securityHeaderFinding->verdict === SecurityHeaderFinding::VERDICT_PASS => $securityHeaderFinding->actual,
            $securityHeaderFinding->verdict === SecurityHeaderFinding::VERDICT_NOT_APPLICABLE => $securityHeaderFinding->note,
            default => 'expected ' . $securityHeaderFinding->expected . ' -- ' . $securityHeaderFinding->note,
        };
    }

    /**
     * The count of actionable findings at the required tier -- the
     * number the FAIL line names.
     */
    private static function requiredFindingCount(SecurityHeaderAudit $securityHeaderAudit): int
    {
        $count = 0;

        foreach ($securityHeaderAudit->findings as $finding) {
            if (!$finding->required || !$finding->isFinding()) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
