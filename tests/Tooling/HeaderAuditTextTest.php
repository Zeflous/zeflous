<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityHeaderAudit;
use Zef\Test\Support\Tooling\HeaderAuditText;

/**
 * The header validator command's text form: one line per declared
 * check -- the header field, its verdict, the expected-versus-actual
 * detail -- closed by the audit's own verdict line, with the count of
 * actionable required findings named when it fails.
 *
 * @internal
 */
#[CoversClass(HeaderAuditText::class)]
final class HeaderAuditTextTest extends TestCase
{
    public function testAFailingAuditSpellsEveryCheckAndNamesTheRequiredFindingCount(): void
    {
        $response = new Response(200, [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=()',
        ]);

        $table = HeaderAuditText::table(SecurityHeaderAudit::over($response, 'http'));

        self::assertSame(
            "X-Content-Type-Options            pass             nosniff\n"
            . "X-Frame-Options                   pass             DENY\n"
            . "Referrer-Policy                   pass             no-referrer\n"
            . "X-Permitted-Cross-Domain-Policies pass             none\n"
            . "Cross-Origin-Opener-Policy        pass             same-origin\n"
            . "Cross-Origin-Resource-Policy      pass             same-origin\n"
            . 'Content-Security-Policy           '
            . 'missing          expected a non-empty value -- the response must carry'
            . " a content security policy\n"
            . 'Strict-Transport-Security         '
            . "not-applicable   an https response must pin its transport for the max-age it declares\n"
            . 'Permissions-Policy                '
            . "pass             camera=()\n"
            . 'Cross-Origin-Embedder-Policy      '
            . 'missing          expected a non-empty value -- advisory: the framework ships no COEP'
            . " posture; report it when the application adds one\n"
            . "\n"
            . 'Security header audit: FAIL (1 required finding(s))',
            mb_rtrim($table, "\n"),
        );
    }

    public function testAPassingAuditClosesWithThePassVerdict(): void
    {
        $response = new Response(200, [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'",
            'Permissions-Policy' => 'camera=()',
        ]);

        self::assertStringEndsWith(
            "\n\nSecurity header audit: PASS\n",
            HeaderAuditText::table(SecurityHeaderAudit::over($response, 'http')),
        );
    }

    public function testAnInvalidRequiredFindingCarriesItsExpectedVersusActualDetail(): void
    {
        $response = new Response(200, [
            'X-Content-Type-Options' => 'allow',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'",
            'Permissions-Policy' => 'camera=()',
        ]);

        $table = HeaderAuditText::table(SecurityHeaderAudit::over($response, 'http'));

        self::assertStringContainsString(
            'X-Content-Type-Options            invalid          expected nosniff --',
            $table,
        );
        self::assertStringContainsString('Security header audit: FAIL (1 required finding(s))', $table);
    }

    public function testTheVerdictCountsEveryActionableRequiredFinding(): void
    {
        $response = new Response(200, ['X-Content-Type-Options' => 'allow']);

        $table = HeaderAuditText::table(SecurityHeaderAudit::over($response, 'https'));

        self::assertStringContainsString(
            'Security header audit: FAIL (9 required finding(s))',
            $table,
        );
    }
}
