<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityHeaderAudit;
use Zef\Framework\Http\SecurityHeaderFinding;
use Zef\Test\Support\Tooling\HeaderAuditJson;

/**
 * The header validator command's JSON form: the verdict, the
 * per-verdict counts, and one record per declared check in
 * declaration order -- the stable shape a provisioning script or a
 * CI check reads the same audit through.
 *
 * @internal
 */
#[CoversClass(HeaderAuditJson::class)]
final class HeaderAuditJsonTest extends TestCase
{
    public function testTheDocumentCarriesTheVerdictTheCountsAndEveryRecord(): void
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

        $document = json_decode(HeaderAuditJson::rendered(SecurityHeaderAudit::over($response, 'http')), true);

        self::assertIsArray($document);
        self::assertTrue($document['passed']);

        self::assertSame(
            [
                SecurityHeaderFinding::VERDICT_PASS => 8,
                SecurityHeaderFinding::VERDICT_INVALID => 0,
                SecurityHeaderFinding::VERDICT_MISSING => 1,
                SecurityHeaderFinding::VERDICT_NOT_APPLICABLE => 1,
            ],
            $document['counts'],
        );

        $records = $document['findings'];
        self::assertIsArray($records);
        self::assertCount(10, $records);

        $embedder = $records[9];
        self::assertIsArray($embedder);
        self::assertSame('Cross-Origin-Embedder-Policy', $embedder['header']);
        self::assertSame(SecurityHeaderFinding::VERDICT_MISSING, $embedder['verdict']);
        self::assertFalse($embedder['required']);
        self::assertSame('a non-empty value', $embedder['expected']);
        self::assertSame('', $embedder['actual']);
    }

    public function testAFailingAuditAnswersItsFalseVerdictAndItsCounts(): void
    {
        $response = new Response(200, ['X-Content-Type-Options' => 'allow']);

        $document = json_decode(HeaderAuditJson::rendered(SecurityHeaderAudit::over($response, 'https')), true);

        self::assertIsArray($document);
        self::assertFalse($document['passed']);

        self::assertSame(
            [
                SecurityHeaderFinding::VERDICT_PASS => 0,
                SecurityHeaderFinding::VERDICT_INVALID => 1,
                SecurityHeaderFinding::VERDICT_MISSING => 9,
                SecurityHeaderFinding::VERDICT_NOT_APPLICABLE => 0,
            ],
            $document['counts'],
        );

        $records = $document['findings'] ?? null;
        self::assertIsArray($records);

        $invalid = $records[0] ?? null;
        self::assertIsArray($invalid);
        self::assertSame('X-Content-Type-Options', $invalid['header']);
        self::assertSame(SecurityHeaderFinding::VERDICT_INVALID, $invalid['verdict']);
        self::assertTrue($invalid['required']);
        self::assertSame('nosniff', $invalid['expected']);
        self::assertSame('allow', $invalid['actual']);
    }
}
